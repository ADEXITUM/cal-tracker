#!/bin/bash
# =============================================================================
# ПРЕДВАРИТЕЛЬНОЕ УСЛОВИЕ: DNS A-запись кал-трекер.рф уже указывает на этот IP.
# Caddy не сможет получить TLS-сертификат без этого.
# =============================================================================
set -euo pipefail

REPO="https://github.com/ADEXITUM/cal-tracker"
DIR="/opt/cal-tracker"
DOMAIN="xn----7sbqavde5bdp.xn--p1ai"   # punycode для кал-трекер.рф
APP_URL="https://${DOMAIN}"
DB_PASS="$(openssl rand -hex 24)"
APP_KEY="base64:$(openssl rand -base64 32)"

# ── 1. Docker ─────────────────────────────────────────────────────────────────
echo "▸ Installing Docker..."
apt-get update -q
apt-get install -y -q curl git
curl -fsSL https://get.docker.com | sh
systemctl enable --now docker

# ── 2. Clone ──────────────────────────────────────────────────────────────────
echo "▸ Cloning repo..."
git clone "$REPO" "$DIR"
cd "$DIR"

# ── 3. Secrets & dirs ─────────────────────────────────────────────────────────
echo "▸ Creating secrets..."
mkdir -p secrets backups
echo "$DB_PASS" > secrets/db_password.txt
chmod 600 secrets/db_password.txt

# ── 4. .env ───────────────────────────────────────────────────────────────────
echo "▸ Writing .env..."
cat > .env <<EOF
APP_NAME=DietTracker
APP_ENV=production
APP_KEY=${APP_KEY}
APP_URL=${APP_URL}
APP_DEBUG=false
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=dt
DB_USERNAME=dt
DB_PASSWORD=${DB_PASS}

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
SANCTUM_STATEFUL_DOMAINS=${DOMAIN}
EOF

# ── 5. Caddyfile (HTTPS + HSTS) ───────────────────────────────────────────────
echo "▸ Writing Caddyfile..."
cat > Caddyfile <<CADDY
${DOMAIN} {
    encode zstd gzip

    header {
        X-Frame-Options DENY
        X-Content-Type-Options nosniff
        Referrer-Policy strict-origin-when-cross-origin
        Strict-Transport-Security "max-age=31536000; includeSubDomains"
    }

    @api path /api/*
    handle @api {
        reverse_proxy backend:8080
    }

    handle {
        reverse_proxy frontend:80
    }
}
CADDY

# ── 6. Build & start ──────────────────────────────────────────────────────────
echo "▸ Building images and starting containers..."
docker compose up -d --build

# ── 7. Wait for healthy ───────────────────────────────────────────────────────
echo "▸ Waiting for DB and backend to be ready..."
for i in $(seq 1 30); do
  STATUS=$(docker compose ps --format json 2>/dev/null | grep -c '"Health":"healthy"' || true)
  [ "$STATUS" -ge 1 ] && break
  [ "$i" -eq 30 ] && { echo "ERROR: services not healthy after 60s"; docker compose logs; exit 1; }
  sleep 2
done
sleep 5  # дать entrypoint отработать миграции

# ── 8. Smoke test ─────────────────────────────────────────────────────────────
echo "▸ Smoke test..."
HTTP=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/v1/auth/me || true)
if [ "$HTTP" = "401" ]; then
  echo "✓ API отвечает (401 — ожидаемо, значит работает)"
else
  echo "⚠ API вернул HTTP $HTTP (ожидался 401). Проверь: docker compose logs backend"
fi

# ── Done ──────────────────────────────────────────────────────────────────────
echo ""
echo "============================================================"
echo " ✓ Готово"
echo "   URL:         ${APP_URL}"
echo "   DB password: ${DB_PASS}   (сохранён в ${DIR}/secrets/db_password.txt)"
echo "   Логи:        docker compose -C ${DIR} logs -f"
echo "============================================================"
