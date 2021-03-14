FROM php:7-apache

RUN apt-get update &&  apt-get install -y curl libcurl4-openssl-dev libicu-dev libpng-dev gnupg zip libzip-dev libonig-dev libxslt-dev && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql curl intl mbstring bcmath gd zip xml xsl

# Install yarn
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
RUN apt-get update && apt-get install -y yarn

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .

# Setup apache2
RUN a2dissite 000-default.conf
COPY ./.docker/symfony.conf /etc/apache2/sites-available/symfony.conf
RUN a2ensite symfony.conf
RUN a2enmod rewrite

USER www-data
RUN composer install -a --no-dev && composer clear-cache
RUN yarn install && yarn build && yarn cache clean
RUN php bin/console --env=prod ckeditor:install --clear=skip

# Use demo env to output logs to stdout
ENV APP_ENV=demo
ENV DATABASE_URL="sqlite:///%kernel.project_dir%/uploads/app.db"

USER root

VOLUME ["/var/www/html/uploads", "/var/www/html/public/media"]
EXPOSE 80