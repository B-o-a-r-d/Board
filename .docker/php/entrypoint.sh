#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# The storage volume is mounted empty over the image's storage/ directory,
# so recreate the framework directory structure every boot (idempotent).
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data storage bootstrap/cache || true

# Public disk symlink (safe to run repeatedly)
php artisan storage:link --quiet || true

# Only the primary application container runs migrations and warms caches.
if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    echo "[entrypoint] Waiting for database..."
    until php artisan db:show --quiet >/dev/null 2>&1; do
        sleep 2
    done

    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        echo "[entrypoint] Running migrations..."
        php artisan migrate --force
    fi

    echo "[entrypoint] Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

exec "$@"
