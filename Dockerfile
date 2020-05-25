ARG PHP_VERSION
FROM php:$PHP_VERSION
RUN apt-get update \
    && apt-get install -y libffi-dev git unzip \
    && docker-php-ext-configure ffi --with-ffi \
    && docker-php-ext-install ffi
WORKDIR /source
RUN curl -sS https://getcomposer.org/installer | php
RUN mv ./composer.phar /usr/local/bin/composer
COPY . /source
RUN composer install