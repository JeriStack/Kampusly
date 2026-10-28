# Native PHP 8.3 backend running under PHP-FPM.
# Only the extensions the app needs are installed.
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        git \
        unzip \
        icu-dev \
        oniguruma-dev \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        opcache

# Composer (copied from the official image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# Dependencies are installed against the mounted source in development.
# Production images should COPY the source and run `composer install --no-dev`.

EXPOSE 9000
CMD ["php-fpm"]
