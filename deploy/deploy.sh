#!/usr/bin/env bash
# Deploy ArtaPedia di VPS Ubuntu. Jalankan dari /var/www/artapedia sebagai user deploy.
# Pemakaian: ./deploy/deploy.sh            (pull + install + migrate + cache + restart)
set -euo pipefail

APP_DIR="/var/www/artapedia"
cd "$APP_DIR"

echo "==> [1/7] Git pull"
git pull --ff-only

echo "==> [1b/7] Permission dulu (sebelum composer, agar package:discover bisa tulis log/cache)"
sudo chown -R www-data:www-data storage bootstrap/cache || chown -R www-data:www-data storage bootstrap/cache || true
sudo chmod -R 775 storage bootstrap/cache || chmod -R 775 storage bootstrap/cache || true

echo "==> [2/7] Composer install (production)"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

echo "==> [3/7] NPM build (abaikan jika gagal / belum ada node_modules)"
if command -v npm >/dev/null 2>&1; then
  (npm ci --no-audit --no-fund && npm run build) || echo "!! npm build gagal, lanjut (pastikan public/build ada)"
else
  echo "!! npm tidak ada, lewati (pastikan public/build ikut ter-commit)"
fi

echo "==> [4/7] Storage link + permission"
php artisan storage:link || true
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "==> [5/7] Migrate + seed default (aman diulang)"
php artisan migrate --force
php artisan db:seed --force || true

echo "==> [6/7] Cache config/route/view"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> [7/7] Restart worker"
sudo supervisorctl reread || true
sudo supervisorctl update || true
sudo supervisorctl restart artapedia-queue:* artapedia-schedule:* || true

echo "==> SELESAI. Cek: php artisan schedule:list && sudo supervisorctl status"
