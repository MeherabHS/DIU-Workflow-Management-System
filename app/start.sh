#!/bin/bash
set -e

echo "[start.sh] Clearing cached config..."
php artisan optimize:clear

echo "[start.sh] Running migrations..."
php artisan migrate --force

echo "[start.sh] Seeding roles and permissions..."
php artisan db:seed --class=RolePermissionSeeder --force

echo "[start.sh] Starting Laravel server on port ${PORT:-8000}..."
php artisan serve --host 0.0.0.0 --port ${PORT:-8000}
