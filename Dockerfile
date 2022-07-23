FROM php:8.1-apache

# Install needed dependencies for PHP build
RUN apt-get update &&  apt-get install -y pkg-config curl libcurl4-openssl-dev libicu-dev \
    libpng-dev libjpeg-dev libfreetype6-dev gnupg zip libzip-dev libjpeg62-turbo-dev libonig-dev libxslt-dev libwebp-dev vim \
    && apt-get -y autoremove && apt-get clean autoclean && rm -rf /var/lib/apt/lists/*

# Install GD with support for multiple formats
RUN docker-php-ext-configure gd \
    --with-webp \
    --with-jpeg \
    --with-freetype \
    && docker-php-ext-install gd

# Install other needed PHP extensions
RUN docker-php-ext-install pdo_mysql curl intl mbstring bcmath zip xml xsl

# Enable opcache and configure it recommended for symfony (see https://symfony.com/doc/current/performance.html)
RUN docker-php-ext-enable opcache; \
	{ \
		echo 'opcache.memory_consumption=256'; \
		echo 'opcache.max_accelerated_files=20000'; \
		echo 'opcache.validate_timestamp=0'; \
        # Configure Realpath cache for performance
        echo 'realpath_cache_size=4096K'; \
        echo 'realpath_cache_ttl=600'; \
      } > /usr/local/etc/php/conf.d/symfony-recommended.ini

# Install yarn
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
RUN apt-get update && apt-get install -y yarn && apt-get -y autoremove && apt-get clean autoclean && rm -rf /var/lib/apt/lists/*

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
RUN yarn install && yarn build && yarn cache clean
RUN php bin/console --env=prod ckeditor:install --clear=skip

# Use demo env to output logs to stdout
ENV APP_ENV=demo
ENV DATABASE_URL="sqlite:///%kernel.project_dir%/uploads/app.db"

USER root

VOLUME ["/var/www/html/uploads", "/var/www/html/public/media"]
EXPOSE 80