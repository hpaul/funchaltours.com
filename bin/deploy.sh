#!/usr/bin/env bash
set -euo pipefail

SERVER="funchaltours"
APP_DIR="/var/www/funchaltours.com"
BRANCH="main"

echo "=== Deploy started at $(date -Iseconds) ==="

echo "Connecting to server..."
ssh "$SERVER" bash -s <<REMOTE
set -euo pipefail
cd "$APP_DIR"

echo "Pulling latest from $BRANCH..."
git pull origin $BRANCH

echo "Building and starting containers..."
docker compose build
docker compose up -d

echo "Running cache commands..."
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan event:cache
docker compose exec -T app php please stache:warm
docker compose exec -T app php please static:warm

echo "=== Deploy finished at \$(date -Iseconds) ==="
REMOTE
