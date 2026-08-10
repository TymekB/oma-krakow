#!/bin/sh
set -e

RUNTIME_INI=/usr/local/etc/php/conf.d/zz-runtime.ini
CACHE_DIR="/app/var/cache/${APP_ENV:-prod}"
JWT_DIR=/app/config/jwt

if [ "${APP_ENV}" = "prod" ]; then
    printf 'opcache.validate_timestamps = 0\nopcache.preload = /app/config/preload.php\nopcache.preload_user = root\n' > "$RUNTIME_INI"
else
    printf 'opcache.validate_timestamps = 1\n' > "$RUNTIME_INI"
fi

mkdir -p /app/var/cache /app/var/log /app/var/sessions /app/public/media
chmod -R 777 /app/var 2>/dev/null || true

# Klucze JWT sa w .gitignore, wiec nie ma ich w obrazie. Trzymamy je w wolumenie,
# zeby przetrwaly redeploy — nowa para uniewaznilaby wydane tokeny.
if [ ! -f "$JWT_DIR/private.pem" ]; then
    mkdir -p "$JWT_DIR"
    openssl genpkey -out "$JWT_DIR/private.pem" \
        -aes256 -pass "pass:${JWT_PASSPHRASE}" \
        -algorithm rsa -pkeyopt rsa_keygen_bits:4096 >/dev/null 2>&1
    openssl pkey -in "$JWT_DIR/private.pem" -passin "pass:${JWT_PASSPHRASE}" \
        -out "$JWT_DIR/public.pem" -pubout >/dev/null 2>&1
    chmod 640 "$JWT_DIR/private.pem"
    chmod 644 "$JWT_DIR/public.pem"
fi

if [ -z "$(ls -A "$CACHE_DIR" 2>/dev/null)" ]; then
    php /app/bin/console cache:warmup --no-interaction || true
fi

exec "$@"
