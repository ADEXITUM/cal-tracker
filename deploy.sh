#!/bin/bash
set -euo pipefail

COMPOSE="docker compose"

# ── Pre-flight ───────────────────────────────────────────────────────────────
[ -f secrets/db_password.txt ] || { echo "ERROR: secrets/db_password.txt not found"; exit 1; }

echo "▸ Pulling latest code..."
git pull origin main

echo "▸ Building images..."
$COMPOSE build --pull

echo "▸ Starting services..."
$COMPOSE up -d

echo "▸ Waiting for db to be healthy..."
for i in $(seq 1 20); do
  $COMPOSE exec -T db pg_isready -U dt -d dt >/dev/null 2>&1 && break
  [ "$i" -eq 20 ] && { echo "ERROR: db never became healthy"; exit 1; }
  sleep 2
done

echo "▸ Running migrations..."
$COMPOSE exec -T backend php artisan migrate --force

echo "▸ Optimising Laravel..."
$COMPOSE exec -T backend php artisan config:cache
$COMPOSE exec -T backend php artisan route:cache
$COMPOSE exec -T backend php artisan event:cache

echo "▸ Services:"
$COMPOSE ps

echo ""
echo "✓ Deploy complete"
