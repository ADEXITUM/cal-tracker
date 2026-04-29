# 08. Deployment

## Цель

Один VPS, `docker compose up -d`, бэкапы автоматически. Без CI/CD на старте — деплой по `deploy.sh`.

## Структура

Корень репо:
```
docker-compose.yml          # base + prod
Caddyfile                   # production reverse proxy
Caddyfile.dev               # local dev (без HTTPS)
.env.example
.env                        # на сервере, gitignored
deploy.sh
Makefile
secrets/db_password.txt     # на сервере, gitignored
backups/                    # bind-mount, gitignored
backend/Dockerfile          # на базе serversideup/php:8.3-fpm-nginx-alpine
frontend/Dockerfile         # multi-stage Node → Nginx
```

## Сервисы (Docker Compose)

5 контейнеров в одной сети `dt-network`:

1. **proxy** — `caddy:2-alpine`, ports 80:80 + 443:443, монтирует Caddyfile, named volume для caddy_data/config
2. **frontend** — multi-stage build: `node:20-alpine` → собирает Vite → копирует `dist/` в `nginx:alpine`. Слушает 80 внутри сети
3. **backend** — `serversideup/php:8.3-fpm-nginx-alpine` (этот образ уже содержит nginx + php-fpm + supervisord, слушает 80). Composer install в build, миграции в entrypoint
4. **db** — `postgres:16-alpine`, named volume `db_data`, healthcheck `pg_isready`
5. **db-backup** — `prodrigestivill/postgres-backup-local:16-alpine`, schedule `@daily`, KEEP 7 daily / 4 weekly / 6 monthly, bind-mount в `./backups` (НЕ named volume — должен пережить смерть docker volumes)

Backend `depends_on: db (condition: service_healthy)`. DB password через Docker secrets, не env.

## Caddyfile

Production (когда появится домен) — стандартный reverse proxy:
- `example.com { ... }` блок
- `/api/*` → `backend:80`, остальное → `frontend:80`
- Auto-HTTPS через Let's Encrypt
- Заголовки: HSTS, X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy strict-origin-when-cross-origin
- Access logs в caddy_data volume

По IP (старт): тот же файл но `:80 { ... }` без HTTPS.

## .env.example

```
APP_NAME=DietTracker
APP_ENV=production
APP_KEY=base64:GENERATE
APP_URL=http://your-vps-ip
APP_DEBUG=false
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=dt
DB_USERNAME=dt
# DB_PASSWORD через secrets/, не здесь
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
SANCTUM_STATEFUL_DOMAINS=
```

## Backend entrypoint

Скрипт `docker/entrypoint.sh`:
1. Wait for DB (poll `php artisan db:show`)
2. `php artisan migrate --force`
3. `php artisan config:cache && route:cache`
4. Запустить supervisord (внутри образа serversideup)

## Frontend nginx config

- SPA fallback `try_files $uri $uri/ /index.html`
- `sw.js` и `manifest.webmanifest` — `Cache-Control: no-cache`
- Иконки PWA — long cache 30 дней
- JS/CSS с хэшем — `Cache-Control: public, immutable, 1y`
- Gzip on

## deploy.sh

```
#!/bin/bash
set -euo pipefail

# Pre-flight: .env и secrets/db_password.txt существуют?
git pull origin main
docker compose build --pull
docker compose up -d
sleep 5
docker compose exec -T backend php artisan optimize
docker compose ps
```

## Makefile

Команды: `up`, `down`, `restart`, `logs`, `shell-backend`, `shell-db` (psql), `migrate`, `seed`, `test`, `deploy`, `backup`.

## Бэкапы

Sidecar `prodrigestivill/postgres-backup-local`:
- `pg_dump` ежедневно в полночь по TZ
- gzip → `/backups/daily/dt-YYYY-MM-DD.sql.gz`
- Раз в неделю → weekly/, раз в месяц → monthly/
- Auto-cleanup по KEEP_DAYS/WEEKS/MONTHS

### Тест восстановления (раз в месяц обязательно)

```
gunzip < backups/daily/dt-LATEST.sql.gz > /tmp/restore.sql
docker compose exec db psql -U dt -c "CREATE DATABASE dt_restore_test;"
docker compose exec -T db psql -U dt dt_restore_test < /tmp/restore.sql
docker compose exec db psql -U dt dt_restore_test -c "SELECT COUNT(*) FROM users;"
docker compose exec db psql -U dt -c "DROP DATABASE dt_restore_test;"
```

Если не работает — бэкапы бесполезны. Раз в месяц проверять.

### Off-site (опц., рекомендуется)

`restic` cron на хосте, синк `./backups/weekly/` в Backblaze B2 раз в неделю. ~$0.005/GB/мес. Страховка от смерти VPS.

## Логирование

- Laravel логи: `storage/logs/laravel.log`, daily channel, 14 дней ротация
- Postgres: stdout (читается через `docker logs`)
- Caddy access: в caddy_data volume

## Первый деплой на чистый VPS (Ubuntu 22.04)

```
curl -fsSL https://get.docker.com | sh
systemctl enable docker
git clone <repo> && cd diet-tracker
mkdir -p secrets && echo "STRONG_PASSWORD" > secrets/db_password.txt
chmod 600 secrets/db_password.txt
cp .env.example .env
# Сгенерировать APP_KEY и вставить
docker compose up -d --build
docker compose ps     # ждём healthy
curl http://your-vps-ip/api/v1/auth/me     # должен 401 — значит работает
```

## Когда появится домен

1. DNS A-запись на IP VPS
2. Поменять Caddyfile с `:80` на `example.com`
3. `docker compose restart proxy` — Caddy сам выпустит Let's Encrypt
4. Поменять `APP_URL` в .env на `https://example.com`
5. `docker compose restart backend`

## Open questions

- GitHub Actions для тестов на push даже без deploy — рекомендую да, как только репо поднят
