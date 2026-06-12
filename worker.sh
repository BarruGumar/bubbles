#!/usr/bin/env bash
set -e

php artisan migrate --force
php artisan storage:link

# Inicia queue worker em segundo plano
php artisan queue:work --sleep=3 --tries=3 &

# Inicia servidor web em primeiro plano
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
