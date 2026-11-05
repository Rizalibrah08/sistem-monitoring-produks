#!/bin/sh
set -e

# Cache konfigurasi, rute, event, dan view memakai nilai .env saat container berjalan.
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    php artisan optimize --no-interaction
fi

exec "$@"
