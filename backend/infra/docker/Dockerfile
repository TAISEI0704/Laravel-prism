FROM php:8.4.7-apache

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update -y && \
    apt-get install -y libpq-dev zip unzip && \
    docker-php-ext-install pdo pdo_pgsql && \
    apt-get clean

RUN a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY ./infra/docker/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html