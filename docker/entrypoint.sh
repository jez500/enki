#!/bin/sh
set -e

# Bootstrap .env from example if not present (e.g. first run without a mounted .env)
if [ ! -f /var/www/.env ]; then
    cp /var/www/.env.example /var/www/.env
fi

# Generate APP_KEY if the current value is empty
if ! grep -qE "^APP_KEY=.+" /var/www/.env; then
    php /var/www/artisan key:generate --force --no-interaction
fi

# Create the SQLite database file if it doesn't already exist
mkdir -p /var/www/database
if [ ! -f /var/www/database/database.sqlite ]; then
    touch /var/www/database/database.sqlite
fi

# Re-create storage directories in case the volume was empty on first mount
mkdir -p \
    /var/www/storage/app/private \
    /var/www/storage/app/public \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/framework/cache \
    /var/www/storage/logs \
    /var/www/storage/skill_data \
    /var/www/bootstrap/cache

# Fix permissions
chown -R www-data:www-data \
    /var/www/storage \
    /var/www/bootstrap/cache \
    /var/www/database
chmod -R 775 \
    /var/www/storage \
    /var/www/bootstrap/cache \
    /var/www/database

# Run database migrations
php /var/www/artisan migrate --force --no-interaction

# Hand off to Supervisor (manages nginx + php-fpm)
exec /usr/bin/supervisord -n -c /etc/supervisord.conf
