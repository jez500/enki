# ─── Stage 1: Frontend assets ─────────────────────────────────────────────────
FROM node:22-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.ts tsconfig.json ./
COPY resources/ resources/
COPY public/ public/
RUN npm run build

# ─── Stage 2: Runtime ─────────────────────────────────────────────────────────
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

# Install PHP dependencies (production only)
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction \
    --prefer-dist

# Copy application source
COPY . .

# Bring in compiled frontend assets from stage 1
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

# Docker config files
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]
