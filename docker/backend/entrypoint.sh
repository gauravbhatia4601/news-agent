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

# Wait for database to be reachable before running migrations
echo "Waiting for database at ${DB_HOST}:${DB_PORT:-5432}..."
MAX_TRIES=30
TRIES=0
until php artisan db:monitor --databases=pgsql > /dev/null 2>&1; do
  TRIES=$((TRIES + 1))
  if [ "$TRIES" -ge "$MAX_TRIES" ]; then
    echo "ERROR: Database not reachable after ${MAX_TRIES} attempts. Starting anyway..."
    break
  fi
  echo "  attempt ${TRIES}/${MAX_TRIES} — retrying in 2s..."
  sleep 2
done

# Run migrations only if explicitly requested via RUN_MIGRATIONS env var
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "Running database migrations..."
  php artisan migrate --force --no-interaction
fi

# Ensure storage link
php artisan storage:link 2>/dev/null || true

# Fix permissions for storage and cache dirs
chmod -R 775 storage bootstrap/cache public/sitemaps 2>/dev/null || true

exec "$@"
