#!/bin/sh
set -e

cd /var/www/html

# Fix permissions first — storage, cache, logs, and sitemaps must be writable by www-data
mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache public/sitemaps
chown -R www-data:www-data storage bootstrap/cache public/sitemaps
chmod -R 775 storage bootstrap/cache public/sitemaps

# Remove any stale log files created by root in previous image layers
rm -f storage/logs/laravel.log storage/logs/laravel-*.log 2>/dev/null || true

# Generate key if missing. In production this means APP_KEY was not injected —
# warn loudly (a fresh key invalidates previously encrypted data) but keep booting.
if [ -z "$APP_KEY" ]; then
  echo "WARNING: APP_KEY is empty${APP_ENV:+ (APP_ENV=$APP_ENV)} — generating a fresh key."
  echo "  If this is production, set APP_KEY in the Coolify environment: encrypted data is invalidated by a new key."
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

# Regenerate sitemaps at boot: deploys wipe public/sitemaps (not a volume),
# and the 30-min cron leaves a 0-30 min window where /sitemaps/* returns
# 404 to crawlers. Regenerate in the background so boot isn't blocked.
(sleep 20; su -s /bin/sh www-data -c "php artisan news:sitemap-generate" >> /var/www/html/storage/logs/sitemap-boot.log 2>&1) &

exec "$@"