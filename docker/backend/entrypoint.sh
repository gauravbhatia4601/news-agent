#!/bin/sh
set -e

cd /var/www/html

# Fix permissions first — storage, cache, logs, and sitemaps must be writable by www-data
mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache public/sitemaps
chown -R www-data:www-data storage bootstrap/cache public/sitemaps
chmod -R 775 storage bootstrap/cache public/sitemaps

# Remove any stale log files created by root in previous image layers
rm -f storage/logs/laravel.log storage/logs/laravel-*.log 2>/dev/null || true

# Generate key if missing — fail fast in production (auto-generating would silently lose encrypted data)
if [ -z "$APP_KEY" ]; then
  if [ "$APP_ENV" = "production" ]; then
    echo "ERROR: APP_KEY is empty in production. Refusing to auto-generate (would invalidate encrypted data). Set APP_KEY before starting."
    exit 1
  fi
  su -s /bin/sh www-data -c "php artisan key:generate --force --no-interaction"
fi

# Cache config, routes, views for production (run as www-data so files are owned correctly)
su -s /bin/sh www-data -c "php artisan config:cache"
su -s /bin/sh www-data -c "php artisan route:cache"
su -s /bin/sh www-data -c "php artisan view:cache"

# Wait for database to be reachable before running migrations
echo "Waiting for database at ${DB_HOST}:${DB_PORT:-5432}..."
MAX_TRIES=30
TRIES=0
until php artisan db:monitor --databases=pgsql > /dev/null 2>&1; do
  TRIES=$((TRIES + 1))
  if [ "$TRIES" -ge "$MAX_TRIES" ]; then
    echo "ERROR: Database not reachable after ${MAX_TRIES} attempts. Starting anyway — deployed DBs may start late."
    break
  fi
  echo "  attempt ${TRIES}/${MAX_TRIES} — retrying in 2s..."
  sleep 2
done

# Run migrations only if explicitly requested via RUN_MIGRATIONS env var
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "Running database migrations..."
  su -s /bin/sh www-data -c "php artisan migrate --force --no-interaction"
fi

# Ensure storage link
su -s /bin/sh www-data -c "php artisan storage:link 2>/dev/null || true"

exec "$@"