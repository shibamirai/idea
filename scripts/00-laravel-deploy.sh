#!/usr/bin/env bash
echo "Running composer"
# composer global require hirak/prestissimo
composer install --no-dev --working-dir=/var/www/html

echo "Caching config..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Running migrations..."
php artisan migrate --force

echo "Viteのインストールと実行"
npm install
npm run build

echo "データベースファイルのパーミッションを読み書き可に変更"
chmod 777 database
chmod 777 database/database.sqlite