#!/bin/sh
set -e

RUNTIME_INI=/usr/local/etc/php/conf.d/zz-runtime.ini
CACHE_DIR="/app/var/cache/${APP_ENV:-prod}"

if [ "${APP_ENV}" = "prod" ]; then
    printf 'opcache.validate_timestamps = 0\nopcache.preload = /app/config/preload.php\nopcache.preload_user = root\n' > "$RUNTIME_INI"
else
    printf 'opcache.validate_timestamps = 1\n' > "$RUNTIME_INI"
fi

mkdir -p /app/var/cache /app/var/log
chmod -R 777 /app/var 2>/dev/null || true

if [ "$1" = "frankenphp" ] && [ -d /app/vendor ] && [ -z "$(ls -A "$CACHE_DIR" 2>/dev/null)" ]; then
    php /app/bin/console cache:warmup --no-interaction || true
fi

exec "$@"
