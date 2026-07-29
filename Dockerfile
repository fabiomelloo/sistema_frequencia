FROM composer:2 AS vendor

WORKDIR /app
COPY . .
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache icu-libs libzip oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install bcmath intl mbstring opcache pcntl pdo_mysql zip \
    && apk del .build-deps

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app .
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/entrypoint.sh /usr/local/bin/sistema-entrypoint

RUN sed -i 's/\r$//' /usr/local/bin/sistema-entrypoint \
    && chmod +x /usr/local/bin/sistema-entrypoint \
    && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

ENTRYPOINT ["sistema-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
