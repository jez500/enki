# ─── Stage 1: Production PHP vendor ───────────────────────────────────────────
FROM jez500/enki-base:php-builder AS php-vendor

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
FROM jez500/enki-base:frontend-builder AS frontend

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
FROM jez500/enki-base:runtime

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
