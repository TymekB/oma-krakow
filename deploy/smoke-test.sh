#!/bin/bash
set -euo pipefail

cd /opt/oma-prod

port="$(grep '^OMA_PORT=' .env | cut -d= -f2)"
host="$(grep '^OMA_DEFAULT_URI=' .env | sed 's|^[^/]*//||; s|/.*||')"

failed=0

for service in app worker mysql; do
    state="$(docker compose ps --format '{{.State}}' "$service" || echo missing)"
    printf '%-14s %s\n' "$service" "$state"
    [ "$state" = running ] || failed=1
done

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
