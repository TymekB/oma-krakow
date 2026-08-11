#!/bin/bash
set -uo pipefail

LOG="${OMA_MEMLOG_FILE:-/opt/oma-prod/memlog.csv}"
KEEP="${OMA_MEMLOG_KEEP:-2000}"

field() {
    awk -v k="$1" '$1 == k { printf "%d", $2 / 1048576; found = 1 } END { if (!found) printf "0" }' \
        /sys/fs/cgroup/memory.stat
}

current=$(( $(cat /sys/fs/cgroup/memory.current 2>/dev/null || echo 0) / 1048576 ))
available=$(awk '/^MemAvailable:/ { printf "%d", $2 / 1024 }' /proc/meminfo)

containers=$(docker stats --no-stream --format '{{.Name}}={{.MemUsage}}' 2>/dev/null |
    sed 's/oma-prod-//; s/-1=/=/' | tr '\n' ' ' | sed 's/ *$//')

if [ ! -s "$LOG" ]; then
    echo 'timestamp,current_mb,anon_mb,file_mb,slab_mb,shmem_mb,available_mb,containers' > "$LOG"
fi

printf '%s,%s,%s,%s,%s,%s,%s,"%s"\n' \
    "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    "$current" "$(field anon)" "$(field file)" "$(field slab)" "$(field shmem)" \
    "$available" "$containers" >> "$LOG"

lines=$(wc -l < "$LOG")
if [ "$lines" -gt "$KEEP" ]; then
    { head -1 "$LOG"; tail -n "$((KEEP - 1))" "$LOG"; } > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
fi
