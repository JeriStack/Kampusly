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
        mbstring \
        intl \
        opcache \
    && { \
        echo "expose_php = Off"; \
        echo "memory_limit = 256M"; \
        echo "upload_max_filesize = 10M"; \
        echo "post_max_size = 10M"; \
        echo "max_execution_time = 30"; \
        echo "date.timezone = Asia/Manila"; \
        echo "session.use_strict_mode = 1"; \
        echo "session.cookie_httponly = 1"; \
    } > /usr/local/etc/php/conf.d/kampusly.ini

# Composer 2 (copied from the official image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# Ensure runtime storage directories exist and are owned by non-root www-data
RUN mkdir -p storage/logs storage/cache storage/tmp storage/uploads \
    && chown -R www-data:www-data /var/www/backend

EXPOSE 9000
CMD ["php-fpm"]
