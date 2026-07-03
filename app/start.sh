#!/usr/bin/env sh
set -e

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

echo "[start.sh] Starting DIUS Management Portal on port ${PORT:-8000}..."
php artisan serve --host 0.0.0.0 --port ${PORT:-8000}