FROM debian:bullseye-slim

# Install needed dependencies for PHP build
#RUN apt-get update &&  apt-get install -y pkg-config curl libcurl4-openssl-dev libicu-dev \
#    libpng-dev libjpeg-dev libfreetype6-dev gnupg zip libzip-dev libjpeg62-turbo-dev libonig-dev libxslt-dev libwebp-dev vim \
#    && apt-get -y autoremove && apt-get clean autoclean && rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get -y install apt-transport-https lsb-release ca-certificates curl zip mariadb-client \
    && curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg  \
    && sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list' \
    && apt-get update && apt-get upgrade -y \
    && apt-get install -y apache2 php8.1 php8.1-fpm php8.1-opcache php8.1-curl php8.1-gd php8.1-mbstring php8.1-xml php8.1-bcmath php8.1-intl php8.1-zip php8.1-xsl php8.1-sqlite3 php8.1-mysql gpg sudo \
    && apt-get -y autoremove && apt-get clean autoclean && rm -rf /var/lib/apt/lists/*;

ENV APACHE_CONFDIR /etc/apache2
ENV APACHE_ENVVARS $APACHE_CONFDIR/envvars

# Create workdir and set permissions if directory does not exists
RUN mkdir -p /var/www/html && chown -R www-data:www-data /var/www/html

# Configure apache 2 (taken from https://github.com/docker-library/php/blob/master/8.2/bullseye/apache/Dockerfile)
# generically convert lines like
#   export APACHE_RUN_USER=www-data
# into
#   : ${APACHE_RUN_USER:=www-data}
#   export APACHE_RUN_USER
# so that they can be overridden at runtime ("-e APACHE_RUN_USER=...")
RUN  sed -ri 's/^export ([^=]+)=(.*)$/: ${\1:=\2}\nexport \1/' "$APACHE_ENVVARS"; \
      set -eux; . "$APACHE_ENVVARS";  \
    # delete the "index.html" that installing Apache drops in here
    	rm -rvf /var/www/html/*; \
    	\
    # logs should go to stdout / stderr
    	ln -sfT /dev/stderr "$APACHE_LOG_DIR/error.log"; \
    	ln -sfT /dev/stdout "$APACHE_LOG_DIR/access.log"; \
    	ln -sfT /dev/stdout "$APACHE_LOG_DIR/other_vhosts_access.log"; \
        chown -R --no-dereference "$APACHE_RUN_USER:$APACHE_RUN_GROUP" "$APACHE_LOG_DIR";

# Enable php-fpm
RUN a2enmod proxy_fcgi setenvif && a2enconf php8.1-fpm

# Configure php-fpm to log to stdout of the container (stdout of PID 1)
# We have to use /proc/1/fd/1 because /dev/stdout or /proc/self/fd/1 does not point to the container stdout (because we use apache as entrypoint)
# We also disable the clear_env option to allow the use of environment variables in php-fpm
RUN { \
    echo '[global]'; \
    echo 'error_log = /proc/1/fd/1'; \
    echo; \
    echo '[www]'; \
    echo 'access.log = /proc/1/fd/1'; \
    echo 'catch_workers_output = yes'; \
    echo 'decorate_workers_output = no'; \
    echo 'clear_env = no'; \
   } | tee "/etc/php/8.1/fpm/pool.d/zz-docker.conf"

# PHP files should be handled by PHP, and should be preferred over any other file type
RUN { \
		echo '<FilesMatch \.php$>'; \
		echo '\tSetHandler application/x-httpd-php'; \
		echo '</FilesMatch>'; \
		echo; \
		echo 'DirectoryIndex disabled'; \
		echo 'DirectoryIndex index.php index.html'; \
		echo; \
		echo '<Directory /var/www/>'; \
		echo '\tOptions -Indexes'; \
		echo '\tAllowOverride All'; \
		echo '</Directory>'; \
	} | tee "$APACHE_CONFDIR/conf-available/docker-php.conf" \
	&& a2enconf docker-php

# Enable opcache and configure it recommended for symfony (see https://symfony.com/doc/current/performance.html)
RUN  \
	{ \
		echo 'opcache.memory_consumption=256'; \
		echo 'opcache.max_accelerated_files=20000'; \
		echo 'opcache.validate_timestamp=0'; \
        # Configure Realpath cache for performance
        echo 'realpath_cache_size=4096K'; \
        echo 'realpath_cache_ttl=600'; \
    } > /etc/php/8.1/fpm/conf.d/symfony-recommended.ini

# Increase upload limit and enable preloading
RUN  \
	{ \
		echo 'upload_max_filesize=256M'; \
		echo 'post_max_size=300M'; \
        echo 'opcache.preload_user=www-data'; \
        echo 'opcache.preload=/var/www/html/config/preload.php'; \
    } > /etc/php/8.1/fpm/conf.d/partdb.ini

# Install node and yarn
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash - && apt-get update && apt-get install -y nodejs yarn && apt-get -y autoremove && apt-get clean autoclean && rm -rf /var/lib/apt/lists/*

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer


# Set working dir
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .

# Setup apache2
RUN a2dissite 000-default.conf
COPY ./.docker/symfony.conf /etc/apache2/sites-available/symfony.conf
RUN a2ensite symfony.conf
RUN a2enmod rewrite

# Install composer and yarn dependencies for Part-DB
USER www-data
RUN composer install -a --no-dev && composer clear-cache
RUN yarn install --network-timeout 600000 && yarn build && yarn cache clean && rm -rf node_modules/

# Use docker env to output logs to stdout
ENV APP_ENV=docker
ENV DATABASE_URL="sqlite:///%kernel.project_dir%/uploads/app.db"

USER root

# Copy entrypoint to /usr/local/bin and make it executable
RUN cp ./.docker/partdb-entrypoint.sh /usr/local/bin/partdb-entrypoint.sh && chmod +x /usr/local/bin/partdb-entrypoint.sh
# Copy apache2-foreground to /usr/local/bin and make it executable
RUN cp ./.docker/apache2-foreground /usr/local/bin/apache2-foreground && chmod +x /usr/local/bin/apache2-foreground
ENTRYPOINT ["partdb-entrypoint.sh"]
CMD ["apache2-foreground"]

# https://httpd.apache.org/docs/2.4/stopping.html#gracefulstop
STOPSIGNAL SIGWINCH

EXPOSE 80
VOLUME ["/var/www/html/uploads", "/var/www/html/public/media"]