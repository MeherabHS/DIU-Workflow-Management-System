#!/usr/bin/env sh
set -e
set -x

echo "[start.sh] PHP version..."
php -v

echo "[start.sh] Laravel version..."
php artisan --version

echo "[start.sh] Clearing config only..."
php artisan config:clear || true

echo "[start.sh] Migration status before running..."
php artisan migrate:status || true

echo "[start.sh] Running migrations..."
php artisan migrate --force -vvv

echo "[start.sh] Seeding roles and permissions..."
php artisan db:seed --class=RolePermissionSeeder --force -vvv

echo "[start.sh] Clearing optimized caches..."
php artisan optimize:clear || true

echo "[start.sh] Starting Laravel server on port ${PORT:-8000}..."
php artisan serve --host 0.0.0.0 --port ${PORT:-8000}
