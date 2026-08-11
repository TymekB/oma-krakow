#!/bin/bash
set -euo pipefail

cd /opt/oma-prod

port="$(grep '^OMA_PORT=' .env | cut -d= -f2)"
host="$(grep '^OMA_DEFAULT_URI=' .env | sed 's|^[^/]*//||; s|/.*||')"

failed=0

for service in app worker mysql rabbitmq; do
    state="$(docker compose ps --format '{{.State}}' "$service" || echo missing)"
    printf '%-14s %s\n' "$service" "$state"
    [ "$state" = running ] || failed=1
done

consuming=nie

for _ in $(seq 1 10); do
    if docker compose logs worker --tail 200 2>/dev/null | grep -q 'Consuming messages from transports'; then
        consuming=tak
        break
    fi
    sleep 3
done

printf '%-14s %s\n' "worker konsumuje" "$consuming"
[ "$consuming" = tak ] || failed=1

for path in / /sklep/ /admin/login; do
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 \
        -H "Host: $host" "http://localhost:${port}${path}" || echo 000)"
    printf '%-14s %s\n' "$path" "$code"
    case "$code" in
        2* | 3*) ;;
        *) failed=1 ;;
    esac
done

exit "$failed"
