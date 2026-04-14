#!/bin/sh
set -eu

[ "${CONTENT_SYNC_ENABLED:-false}" = "true" ] || exit 0

PID_FILE=${CONTENT_SYNC_PID_FILE:-/tmp/content-sync.pid}

if [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
    exit 0
fi

(
    while true; do
        /usr/local/bin/content-sync-once.sh || true
        sleep "${CONTENT_SYNC_INTERVAL:-300}"
    done
) >/var/www/html/storage/logs/content-sync.log 2>&1 &

echo $! > "$PID_FILE"
