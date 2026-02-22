#!/bin/sh
set -e

cd /var/www/html

# Generate key if missing
if [ -z "$APP_KEY" ]; then
  php artisan key:generate --force --no-interaction
fi

# Cache config, routes, views for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force --no-interaction

# Ensure storage link
php artisan storage:link 2>/dev/null || true

# Fix permissions
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
