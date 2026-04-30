# ─── Stage 1: Production PHP vendor ───────────────────────────────────────────
FROM php:8.3-alpine AS php-vendor

RUN apk add --no-cache sqlite-dev libzip-dev oniguruma-dev unzip \
    && docker-php-ext-install pdo pdo_sqlite mbstring bcmath zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction \
    --prefer-dist

# ─── Stage 2: Frontend assets ─────────────────────────────────────────────────
# Uses PHP as base so `php artisan wayfinder:generate` works during npm build.
# Installs dev dependencies (laravel/boost etc.) needed for artisan to boot.
FROM php:8.3-alpine AS frontend

RUN apk add --no-cache \
        nodejs \
        npm \
        sqlite-dev \
        libzip-dev \
        oniguruma-dev \
        unzip \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        mbstring \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction \
    --prefer-dist

# PHP app files needed for artisan to boot
COPY artisan artisan
COPY .env.example .env
COPY bootstrap/ bootstrap/
COPY app/ app/
COPY config/ config/
COPY routes/ routes/
COPY database/ database/

RUN mkdir -p \
        storage/framework/views \
        storage/framework/cache \
        storage/framework/sessions \
        bootstrap/cache

# Node build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.ts tsconfig.json ./
COPY resources/ resources/
COPY public/ public/
RUN npm run build

# ─── Stage 3: Runtime ─────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine

# System dependencies and PHP extensions
RUN apk add --no-cache \
        nginx \
        supervisor \
        sqlite \
        sqlite-dev \
        libzip-dev \
        oniguruma-dev \
        curl \
        unzip \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        mbstring \
        bcmath \
        zip \
        opcache \
    && docker-php-ext-enable opcache

# OPcache tuning for production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# Pass Docker env vars through to PHP-FPM worker processes
RUN echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Production vendor from the php-vendor stage (no dev dependencies)
COPY --from=php-vendor /app/vendor vendor/

# Copy application source
COPY . .

# Bring in compiled frontend assets from stage 2
COPY --from=frontend /app/public/build public/build

# Initialise storage directory structure
RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache \
        storage/logs \
        storage/skill_data \
        bootstrap/cache \
        database \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database

# Regenerate package discovery cache using only the installed (no-dev) vendor
RUN cp .env.example .env \
    && php artisan package:discover --ansi \
    && rm .env

# Docker config files
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]
