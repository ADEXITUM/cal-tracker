# DietTracker

Self-hosted PWA для трекинга веса, состава тела, КБЖУ, тренировок, сна, настроения.

## Стек

- **Backend:** Laravel 11 + Sanctum, PHP 8.3
- **Frontend:** Vue 3 + Vite + TypeScript + Pinia + Tailwind
- **DB:** PostgreSQL 16
- **Proxy:** Caddy 2
- **Deploy:** Docker Compose

## Локальный запуск

```bash
cp .env.example .env
# Заполнить .env
make up
```

Открыть http://localhost

## Команды

```
make up          — поднять все контейнеры
make down        — остановить
make restart     — рестарт
make logs        — логи всех контейнеров
make shell-backend — bash в backend
make shell-db    — psql в postgres
make migrate     — php artisan migrate
make seed        — php artisan db:seed
make test        — запустить все тесты
make deploy      — деплой (git pull + build + up)
make backup      — ручной бэкап БД
```

## Деплой на VPS

См. [docs/08-deployment.md](docs/08-deployment.md)
