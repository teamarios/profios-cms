FROM php:8.3-fpm-alpine

RUN docker-php-ext-install pdo pdo_mysql
RUN pecl install redis && docker-php-ext-enable redis

WORKDIR /var/www/html
