#!/usr/bin/env sh
set -e

export PORT="${PORT:-8080}"

echo "[start.sh] Preparing Nginx on port ${PORT}..."
envsubst '$PORT' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

echo "[start.sh] Preparing Laravel runtime..."
php artisan config:clear || true

echo "[start.sh] Running database migrations..."
php artisan migrate --force

echo "[start.sh] Syncing roles and permissions..."
php artisan db:seed --class=RolePermissionSeeder --force

echo "[start.sh] Linking public storage..."
php artisan storage:link || true

echo "[start.sh] Clearing optimized caches..."
php artisan optimize:clear || true

echo "[start.sh] Starting PHP-FPM and Nginx..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
