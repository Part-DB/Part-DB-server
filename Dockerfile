ARG BASE_IMAGE=debian:bookworm-slim
ARG PHP_VERSION=8.3

FROM ${BASE_IMAGE} AS base
ARG PHP_VERSION

# Install needed dependencies for PHP build
#RUN apt-get update &&  apt-get install -y pkg-config curl libcurl4-openssl-dev libicu-dev \
#    libpng-dev libjpeg-dev libfreetype6-dev gnupg zip libzip-dev libjpeg62-turbo-dev libonig-dev libxslt-dev libwebp-dev vim \
#    && apt-get -y autoremove && apt-get clean autoclean && rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get -y install \
      apt-transport-https \
      lsb-release \
      ca-certificates \
      curl \
      zip \
      mariadb-client \
      postgresql-client \
    && curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg  \
    && sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list' \
    && apt-get update && apt-get upgrade -y \
    && apt-get install -y \
      apache2 \
      php${PHP_VERSION} \
      php${PHP_VERSION}-fpm \
      php${PHP_VERSION}-opcache \
      php${PHP_VERSION}-curl \
      php${PHP_VERSION}-gd \
      php${PHP_VERSION}-mbstring \
      php${PHP_VERSION}-xml \
      php${PHP_VERSION}-bcmath \
      php${PHP_VERSION}-intl \
      php${PHP_VERSION}-zip \
      php${PHP_VERSION}-xsl \
      php${PHP_VERSION}-sqlite3 \
      php${PHP_VERSION}-mysql \
      php${PHP_VERSION}-pgsql \
      gpg \
      sudo \
    && apt-get -y autoremove && apt-get clean autoclean && rm -rf /var/lib/apt/lists/* \
# Create workdir and set permissions if directory does not exists
    && mkdir -p /var/www/html \
    && chown -R www-data:www-data /var/www/html \
# delete the "index.html" that installing Apache drops in here
    && rm -rvf /var/www/html/*

# Install node and yarn
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - && \
    echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list && \
    curl -sL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get update && apt-get install -y \
      nodejs \
      yarn \
    && apt-get -y autoremove && apt-get clean autoclean && rm -rf /var/lib/apt/lists/*

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV APACHE_CONFDIR=/etc/apache2
ENV APACHE_ENVVARS=$APACHE_CONFDIR/envvars

# Configure apache 2 (taken from https://github.com/docker-library/php/blob/master/8.2/bullseye/apache/Dockerfile)
# generically convert lines like
#   export APACHE_RUN_USER=www-data
# into
#   : ${APACHE_RUN_USER:=www-data}
#   export APACHE_RUN_USER
# so that they can be overridden at runtime ("-e APACHE_RUN_USER=...")
RUN  sed -ri 's/^export ([^=]+)=(.*)$/: ${\1:=\2}\nexport \1/' "$APACHE_ENVVARS"; \
      set -eux; . "$APACHE_ENVVARS";  \
    	\
    # logs should go to stdout / stderr
    	ln -sfT /dev/stderr "$APACHE_LOG_DIR/error.log"; \
    	ln -sfT /dev/stdout "$APACHE_LOG_DIR/access.log"; \
    	ln -sfT /dev/stdout "$APACHE_LOG_DIR/other_vhosts_access.log"; \
        chown -R --no-dereference "$APACHE_RUN_USER:$APACHE_RUN_GROUP" "$APACHE_LOG_DIR";

# ---

FROM scratch AS apache-config
ARG PHP_VERSION
# Configure php-fpm to log to stdout of the container (stdout of PID 1)
# We have to use /proc/1/fd/1 because /dev/stdout or /proc/self/fd/1 does not point to the container stdout (because we use apache as entrypoint)
# We also disable the clear_env option to allow the use of environment variables in php-fpm
COPY <<EOF /etc/php/${PHP_VERSION}/fpm/pool.d/zz-docker.conf
[global]
error_log = /proc/1/fd/1

[www]
access.log = /proc/1/fd/1
catch_workers_output = yes
decorate_workers_output = no
clear_env = no
EOF

# PHP files should be handled by PHP, and should be preferred over any other file type
COPY <<EOF /etc/apache2/conf-available/docker-php.conf
<FilesMatch \\.php$>
	SetHandler application/x-httpd-php
</FilesMatch>

DirectoryIndex disabled
DirectoryIndex index.php index.html

<Directory /var/www/>
	Options -Indexes
	AllowOverride All
</Directory>
EOF

# Enable opcache and configure it recommended for symfony (see https://symfony.com/doc/current/performance.html)
COPY <<EOF /etc/php/${PHP_VERSION}/fpm/conf.d/symfony-recommended.ini
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamp=0
# Configure Realpath cache for performance
realpath_cache_size=4096K
realpath_cache_ttl=600
EOF

# Increase upload limit and enable preloading
COPY <<EOF /etc/php/${PHP_VERSION}/fpm/conf.d/partdb.ini
upload_max_filesize=256M
post_max_size=300M
opcache.preload_user=www-data
opcache.preload=/var/www/html/config/preload.php
log_limit=8096
EOF

COPY ./.docker/symfony.conf /etc/apache2/sites-available/symfony.conf

# ---

FROM base
ARG PHP_VERSION

# Set working dir
WORKDIR /var/www/html
COPY --from=apache-config / /
COPY --chown=www-data:www-data . .

# Setup apache2
RUN a2dissite 000-default.conf && \
    a2ensite symfony.conf && \
# Enable php-fpm
    a2enmod proxy_fcgi setenvif && \
    a2enconf php${PHP_VERSION}-fpm && \
    a2enconf docker-php && \
    a2enmod rewrite

# Install composer and yarn dependencies for Part-DB
USER www-data
RUN composer install -a --no-dev && \
    composer clear-cache
RUN yarn install --network-timeout 600000 && \
    yarn build && \
    yarn cache clean && \
    rm -rf node_modules/

# Use docker env to output logs to stdout
ENV APP_ENV=docker
ENV DATABASE_URL="sqlite:///%kernel.project_dir%/uploads/app.db"

USER root

# Replace the php version placeholder in the entry point, with our php version
RUN sed -i "s/PHP_VERSION/${PHP_VERSION}/g" ./.docker/partdb-entrypoint.sh

# Copy entrypoint and apache2-foreground to /usr/local/bin and make it executable
RUN install ./.docker/partdb-entrypoint.sh /usr/local/bin && \
    install ./.docker/apache2-foreground /usr/local/bin
ENTRYPOINT ["partdb-entrypoint.sh"]
CMD ["apache2-foreground"]

# https://httpd.apache.org/docs/2.4/stopping.html#gracefulstop
STOPSIGNAL SIGWINCH

EXPOSE 80
VOLUME ["/var/www/html/uploads", "/var/www/html/public/media"]
