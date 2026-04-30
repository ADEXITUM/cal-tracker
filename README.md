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

## Документация

Для агентов и новых разработчиков: [AGENTS.md](AGENTS.md) — входная дверь.

Дальше:
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — что где живёт
- [docs/FORMULAS.md](docs/FORMULAS.md) — все числа в одном месте (TDEE, mode, macros, пороги insights)
- [docs/06-insights.md](docs/06-insights.md) — TDEE, режимы дня, правила подсказок
- [docs/02-database.md](docs/02-database.md) — схема БД
- [docs/03-api.md](docs/03-api.md) — эндпоинты
- [docs/04-frontend.md](docs/04-frontend.md) — структура UI
- [docs/05-design.md](docs/05-design.md) — токены, темы

## Деплой на VPS

См. [docs/08-deployment.md](docs/08-deployment.md)
