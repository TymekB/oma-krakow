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

docker compose stop worker >/dev/null 2>&1 || true

docker compose run --rm -T --no-deps app \
    php -d memory_limit=-1 bin/console doctrine:migrations:migrate \
        --no-interaction --allow-no-migration < /dev/null

docker compose up -d --wait --wait-timeout 300 --remove-orphans

rm -f /etc/cron.d/oma-messenger

docker image prune -f
