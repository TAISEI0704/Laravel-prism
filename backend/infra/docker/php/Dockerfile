FROM php:8.4.7-apache

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    TZ=Asia/Tokyo

RUN apt-get update -y && \
    apt-get install -y libpq-dev zip unzip && \
    docker-php-ext-install pdo pdo_pgsql && \
    a2enmod rewrite && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY ./infra/docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html/

WORKDIR /var/www/html

RUN composer install --no-dev --optimize-autoloader --no-interaction && \
    chown -R www-data:www-data /var/www/html