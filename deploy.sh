#!/usr/bin/env bash
set -e

echo "==> Pulling latest code..."
git pull origin master

echo "==> Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

echo "==> Enabling maintenance mode..."
php artisan down --retry=5

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Caching config / routes / views / events..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Installing JS dependencies..."
npm ci

echo "==> Building frontend assets..."
npm run build

echo "==> Clearing compiled views cache (post-build)..."
php artisan view:clear

echo "==> Disabling maintenance mode..."
php artisan up

echo ""
echo "Deploy complete."
