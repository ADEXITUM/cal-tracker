# DietTracker — Plan

Self-hosted PWA для трекинга веса, состава тела, КБЖУ, тренировок, сна, настроения. Личное использование, 3+ юзеров через мультиаккаунт. Деплой Docker Compose на VPS.

## Стек (зафиксирован)

- **Backend:** Laravel 11 + Sanctum, PHP 8.3, использовать образ `serversideup/php:8.3-fpm-nginx-alpine`
- **Frontend:** Vue 3 + Vite + TS strict + Pinia + Vue Router 4 + Tailwind
- **DB:** PostgreSQL 16
- **UI:** lucide-vue-next, vue3-apexcharts, @vueuse/motion, Inter + JetBrains Mono
- **PWA:** vite-plugin-pwa + Workbox, autoUpdate
- **Proxy:** Caddy 2
- **Backups:** prodrigestivill/postgres-backup-local sidecar

**НЕ используем:** Redis, очереди, push-уведомления, i18n (только русский), CI/CD на старте, экспорт/импорт, AI-чат, gamification, справочник упражнений.

## Принципы

- **PSR-12, declare(strict_types=1)**, FormRequest для валидации, ResourceCollection для ответов
- **TS strict, без `any`** без явного комментария
- **Composition API** (`<script setup>`), Pinia стор для всего state
- **Тесты обязательны:** Pest для backend, Vitest для frontend. Без тестов PR не принимается.
- **Vertical slices:** одна фича = DB → API → UI, не отдельными неделями
- **Sanctum-токены в IndexedDB** на фронте (не localStorage). Multi-account как в Telegram.
- **UUID v7 в публичных API**, BIGSERIAL внутри
- **Денормализация на чтение:** `GET /days/{date}` отдаёт всё одним запросом
- **Идемпотентность:** POST для записи принимают `Idempotency-Key` header (для оффлайн-синхронизации)
- **Паттерн `mode`:** не выбираем фазу, считаем автоматически из delta(goal, TDEE)

## Порядок чтения для агента

1. `docs/01-product.md` — что строим, user stories, не-цели
2. `docs/02-database.md` — схема БД (источник правды для типов)
3. `docs/03-api.md` — endpoints и контракты
4. `docs/04-frontend.md` — структура Vue, stores, типы
5. `docs/05-design.md` — токены, темы, ключевые компоненты
6. `docs/06-insights.md` — TDEE, режимы, базовые подсказки
7. `docs/07-pwa-offline.md` — PWA-стратегия, offline queue
8. `docs/08-deployment.md` — деплой, бэкапы
9. `docs/09-roadmap.md` — фазы и порядок работы

## Структура репо (агент создаёт сам)

```
diet-tracker/
├── backend/        # Laravel
├── frontend/       # Vue
├── docs/           # эти файлы
├── docker-compose.yml
├── Caddyfile
├── deploy.sh
├── Makefile
└── .env.example
```

## Что делать в случае неоднозначности

- Если в плане противоречие — указать на него и спросить, не выбирать молча
- Не делать "улучшений" сверх плана без обсуждения
- В каждом доке секция Open questions — там решения, которые я оставил на агента или на потом
