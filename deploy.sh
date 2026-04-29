#!/bin/bash
set -euo pipefail

# Pre-flight checks
[ -f .env ] || { echo "ERROR: .env not found"; exit 1; }
[ -f secrets/db_password.txt ] || { echo "ERROR: secrets/db_password.txt not found"; exit 1; }

git pull origin main
docker compose build --pull
docker compose up -d
sleep 5
docker compose exec -T backend php artisan optimize
docker compose ps
