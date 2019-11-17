FROM php:7-apache

RUN apt-get update &&  apt-get install -y curl libcurl4-openssl-dev libicu-dev libpng-dev gnupg zip libzip-dev

RUN docker-php-ext-install pdo_mysql curl intl mbstring bcmath gd zip

# Install composer
#RUN curl --silent --show-error https://getcomposer.org/installer | php

# Install yarn
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
RUN apt-get update && apt-get install -y yarn

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer global require hirak/prestissimo

WORKDIR /var/www/html
COPY . .

# Setup apache2
RUN a2dissite 000-default.conf
COPY ./.docker/symfony.conf /etc/apache2/sites-available/symfony.conf
RUN a2ensite symfony.conf

ENV APP_ENV=demo

RUN composer install -a --no-dev
RUN yarn install && yarn build
RUN php bin/console ckeditor:install --clear=skip

RUN php bin/console cache:warmup

# Clean up composer cache
RUN rm -rf /root/.composer

