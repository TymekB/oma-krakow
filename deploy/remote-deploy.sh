#!/bin/bash
set -euo pipefail

STACK_DIR=/opt/oma-prod
IMAGE="${1:?podaj tag obrazu}"

cd "$STACK_DIR"

if [ ! -f .env ]; then
    echo "Brak $STACK_DIR/.env — konfiguracja stacku musi juz istniec na serwerze" >&2
    exit 1
fi

grep -v '^OMA_IMAGE=' .env > .env.next
echo "OMA_IMAGE=$IMAGE" >> .env.next
mv .env.next .env

docker compose pull --quiet

docker compose up -d --wait --wait-timeout 180 mysql

docker compose run --rm -T --no-deps app \
    php -d memory_limit=-1 bin/console doctrine:migrations:migrate \
        --no-interaction --allow-no-migration < /dev/null

docker compose up -d --wait --wait-timeout 300 --remove-orphans

cat > /etc/cron.d/oma-messenger <<'CRON'
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

*/5 * * * * root docker exec oma-prod-app-1 php -d memory_limit=256M bin/console messenger:consume main catalog_promotion_removal --time-limit=45 --limit=100 --no-interaction -q >/dev/null 2>&1
CRON
chmod 644 /etc/cron.d/oma-messenger

docker image prune -f
