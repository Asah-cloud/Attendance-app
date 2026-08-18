#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/attendance-app"
PHP_FPM_SERVICE="php8.4-fpm"
WORKER_GROUP="attendance-worker:*"

cd "$APP_DIR"

echo "==> Pulling latest code"
git pull origin main

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader

echo "==> Installing and building frontend assets"
npm install
npm run build

echo "==> Running database migrations"
php artisan migrate --force

echo "==> Clearing cached config/routes/views"
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "==> Ensuring storage symlink exists"
php artisan storage:link || true

echo "==> Fixing ownership and permissions"
sudo chown -R ubuntu:www-data "$APP_DIR"
sudo find "$APP_DIR/storage" -type d -exec chmod 775 {} \;
sudo find "$APP_DIR/storage" -type f -exec chmod 664 {} \;
sudo chmod -R 775 "$APP_DIR/bootstrap/cache"

echo "==> Restarting queue workers so they pick up the new code/config"
sudo supervisorctl restart "$WORKER_GROUP"

echo "==> Reloading PHP-FPM to clear opcache"
sudo systemctl reload "$PHP_FPM_SERVICE"

echo "==> Deploy complete"
