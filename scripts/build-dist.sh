#!/usr/bin/env bash
# Build dist/: a self-contained copy of CIDASH to upload to the LAMP server.
# Production PHP dependencies and compiled assets are included, so the server
# needs neither Composer nor Node.
#
# Local: ddev exec scripts/build-dist.sh
#
# dist/ is built from the current commit (git archive), never from local files,
# so it contains no .env, no SQLite database and nothing under storage/ except its
# empty folder skeleton. It can be uploaded over an existing install without
# touching its data. dist/REVISION records the commit. Upload and server steps:
# ARCHITECTURE.md §7.
set -euo pipefail

cd "$(dirname "$0")/.."
DIST="$PWD/dist"

if [ -n "$(git status --porcelain)" ]; then
    echo "Commit or stash your changes first: dist/ is built from the current commit." >&2
    exit 1
fi

echo "→ Building assets"
npm ci
npm run build

echo "→ Exporting $(git rev-parse --short HEAD)"
rm -rf "$DIST"
mkdir -p "$DIST"
git archive HEAD app bootstrap config database lang public resources routes storage \
    artisan composer.json composer.lock .env.example | tar -x -C "$DIST"
cp -a public/build "$DIST/public/"
git rev-parse HEAD > "$DIST/REVISION"
# The version shown in the footer: the latest release in the changelog.
grep -m1 -oE '^## [0-9]+\.[0-9]+\.[0-9]+' CHANGELOG.md | cut -c4- > "$DIST/VERSION"

# Sources only needed for development.
rm -rf "$DIST/resources/js" "$DIST/resources/css" \
    "$DIST/database/factories" "$DIST/database/seeders" \
    "$DIST/storage/framework/testing"

echo "→ Installing production PHP dependencies"
composer install --working-dir="$DIST" --no-dev --optimize-autoloader \
    --no-interaction --no-progress

echo "✓ dist/ ready ($(du -sh "$DIST" | cut -f1), $(git rev-parse --short HEAD))"
