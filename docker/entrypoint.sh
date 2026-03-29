#!/bin/sh
set -e

cd /var/www/html

# Install dependencies only when vendor is missing (common with bind mounts).
if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Tune Laravel bootstrap cost for Docker on Windows bind mounts.
# Set LARAVEL_OPTIMIZE=0 in compose/.env when you want uncached local behavior.
if [ "${LARAVEL_OPTIMIZE:-1}" = "1" ]; then
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
else
  php artisan config:clear || true
  php artisan route:clear || true
  php artisan view:clear || true
fi

exec "$@"
