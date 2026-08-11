#!/bin/sh
set -eu

umask 077

SSH_TARGET="${OMA_SSH_TARGET:-}"
SSH_PORT="${OMA_SSH_PORT:-10123}"
REMOTE_DIR="${OMA_REMOTE_DIR:-/var/lib/docker/volumes/oma-prod_db_backup/_data}"
LOCAL_DIR="${OMA_LOCAL_DIR:-$HOME/Backups/oma}"
KEEP_DAYS="${OMA_KEEP_DAYS:-30}"

mkdir -p "$LOCAL_DIR"
chmod 700 "$LOCAL_DIR"

if [ -n "$SSH_TARGET" ]; then
    rsync -az --partial \
        -e "ssh -p $SSH_PORT -o BatchMode=yes -o ConnectTimeout=20" \
        "$SSH_TARGET:$REMOTE_DIR/" "$LOCAL_DIR/"
else
    rsync -az --partial "$REMOTE_DIR/" "$LOCAL_DIR/"
fi

find "$LOCAL_DIR" -maxdepth 1 -name '*.sql.gz' -type f -mtime "+$KEEP_DAYS" -delete

BROKEN=0

for archive in "$LOCAL_DIR"/*.sql.gz; do
    [ -e "$archive" ] || continue

    chmod 600 "$archive"

    if ! gzip -t "$archive" 2>/dev/null; then
        echo "uszkodzona kopia: $archive" >&2
        BROKEN=$((BROKEN + 1))
    fi
done

COUNT=$(find "$LOCAL_DIR" -maxdepth 1 -name '*.sql.gz' -type f | wc -l | tr -d ' ')
NEWEST=$(ls -t "$LOCAL_DIR"/*.sql.gz 2>/dev/null | head -1)
SIZE=$(du -sh "$LOCAL_DIR" | cut -f1)

printf '%s | kopie: %s | najnowsza: %s | zajete: %s\n' \
    "$(date '+%Y-%m-%d %H:%M')" "$COUNT" "$(basename "${NEWEST:-brak}")" "$SIZE"

if [ "$COUNT" -eq 0 ]; then
    echo "brak jakiejkolwiek kopii w $LOCAL_DIR" >&2
    exit 1
fi

if [ "$BROKEN" -gt 0 ]; then
    exit 1
fi
