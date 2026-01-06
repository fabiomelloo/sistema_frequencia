FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql zip gd bcmath mbstring pcntl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Ensure git safe directory to avoid "dubious ownership" when repo is mounted/copied
RUN git config --global --add safe.directory /var/www/html || true

# Install initial dependencies (may run again after copying full repo)
RUN composer install --no-interaction --no-plugins --no-scripts --prefer-dist || true

# Copy app
COPY . /var/www/html

# Ensure git safe directory again and install vendors matching PHP in container
RUN git config --global --add safe.directory /var/www/html || true
RUN composer install --no-interaction --prefer-dist --no-scripts

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

EXPOSE 9000
CMD ["php-fpm"]
