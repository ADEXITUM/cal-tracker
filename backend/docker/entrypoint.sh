#!/bin/bash
set -e

# Fix storage permissions at runtime
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "[entrypoint] Waiting for database..."
until php artisan db:show --quiet 2>/dev/null; do
  sleep 2
done
echo "[entrypoint] Database ready"

php artisan migrate --force
php artisan config:cache
php artisan route:cache

echo "[entrypoint] Starting services..."
exec docker-php-serversideup-entrypoint /init
