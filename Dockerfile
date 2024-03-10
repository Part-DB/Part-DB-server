FROM debian:bookworm-slim


RUN apt-get update && apt-get -y install apt-transport-https lsb-release ca-certificates curl zip mariadb-client file acl \
    && curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg  \
    && sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list' \
    && apt-get update && apt-get upgrade -y \
    && apt-get install -y apache2 php8.3 php8.3-fpm php8.3-opcache php8.3-curl php8.3-gd php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-intl php8.3-zip php8.3-xsl php8.3-sqlite3 php8.3-mysql gpg \
    && apt-get -y autoremove && apt-get clean autoclean && rm -rf /var/lib/apt/lists/*;

# Create workdir and set permissions if directory does not exists
RUN mkdir -p /app
WORKDIR /app

# Copy config files for php and caddy
ENV PHP_INI_DIR="/etc/php/8.3/"
COPY --link .docker/frankenphp/conf.d/app.ini $PHP_INI_DIR/conf.d/
COPY --chmod=755 .docker/frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link .docker/frankenphp/Caddyfile /etc/caddy/Caddyfile
COPY --link .docker/frankenphp/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/
COPY --link .docker/frankenphp/worker.Caddyfile /etc/caddy/worker.Caddyfile

#RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Install node and yarn
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash - && apt-get update && apt-get install -y nodejs yarn && apt-get -y autoremove && apt-get clean autoclean && rm -rf /var/lib/apt/lists/*

# Install FrankenPHP
COPY --from=dunglas/frankenphp:1-php8.3 /usr/local/bin/frankenphp /usr/local/bin/frankenphp

# And configure it
ENV FRANKENPHP_CONFIG="import worker.Caddyfile"

# Install composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# prevent the reinstallation of vendors at every changes in the source code
COPY --link composer.* symfony.* ./
RUN set -eux; \
	composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

# copy sources
COPY --link . ./

# Install composer and yarn dependencies for Part-DB
RUN set -eux; \
	mkdir -p var/cache var/log; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; sync;

RUN yarn install --network-timeout 600000 && yarn build && yarn cache clean && rm -rf node_modules/

# Use docker env to output logs to stdout
ENV APP_ENV=docker
ENV DATABASE_URL="sqlite:///%kernel.project_dir%/uploads/app.db"

USER root

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# https://httpd.apache.org/docs/2.4/stopping.html#gracefulstop
STOPSIGNAL SIGWINCH

VOLUME ["/var/www/html/uploads", "/var/www/html/public/media"]

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1

# See https://caddyserver.com/docs/conventions#file-locations for details
ENV XDG_CONFIG_HOME /config
ENV XDG_DATA_HOME /data

EXPOSE 80
EXPOSE 443
EXPOSE 443/udp
EXPOSE 2019