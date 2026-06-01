web: php artisan migrate --force && php artisan storage:link && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
worker: php artisan queue:work --tries=3 --sleep=3 --max-time=3600
