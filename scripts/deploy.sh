#!/usr/bin/env bash
# Deploy CIDASH to a plain LAMP server over SSH.
#
# Usage: DEPLOY_TARGET=user@host:/path/to/cidash scripts/deploy.sh
#
# Runs from a development machine: assets are built here (no Node on the server),
# code is sent with rsync, and Composer/Artisan run on the server.
# The server's data (.env, database/*.sqlite, storage/) is never overwritten.
#
# First install on the server (once): create .env from .env.example with
# APP_ENV=production and APP_DEBUG=false, run `php artisan key:generate`,
# `touch database/database.sqlite`, create the storage skeleton with
# `mkdir -p storage/app/private storage/app/public storage/framework/{cache/data,sessions,views} storage/logs`,
# point the DocumentRoot to public/ and add the cron entry: * * * * * cd /path/to/cidash && php artisan schedule:run >> /dev/null 2>&1
set -euo pipefail

: "${DEPLOY_TARGET:?Set DEPLOY_TARGET=user@host:/path/to/cidash}"
HOST="${DEPLOY_TARGET%%:*}"
DIR="${DEPLOY_TARGET#*:}"

cd "$(dirname "$0")/.."

echo "→ Building assets"
npm ci
npm run build

echo "→ Maintenance mode"
ssh "$HOST" "cd '$DIR' && php artisan down --retry=60 || true"

echo "→ Syncing code to $DEPLOY_TARGET"
rsync -az --delete \
    --exclude='/.git' \
    --exclude='/.ddev' \
    --exclude='/.github' \
    --exclude='/node_modules' \
    --exclude='/vendor' \
    --exclude='/tests' \
    --exclude='/.env' \
    --exclude='/database/*.sqlite*' \
    --exclude='/storage' \
    --exclude='/public/storage' \
    --exclude='/public/hot' \
    ./ "$DEPLOY_TARGET/"

echo "→ Installing and migrating on the server"
ssh "$HOST" "cd '$DIR' && set -e
    composer install --no-dev --optimize-autoloader --no-interaction
    php artisan cidash:backup
    php artisan migrate --force
    php artisan optimize
    php artisan up"

echo "✓ Deployed"
