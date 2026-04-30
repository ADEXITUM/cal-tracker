# AGENTS.md — для агентов и людей в режиме «быстро войти в проект»

> Это входная дверь для любого AI-агента (Claude, Codex, Cursor, ...) и для
> человека, который только что открыл репозиторий. Здесь — *что это, как
> устроено, что НЕ делать, куда смотреть дальше*. Всё детальное —
> в `docs/`.

## Что это

PWA-трекер веса, состава тела, КБЖУ, тренировок. Self-hosted, личное использование, мульти-аккаунт как в Telegram. Один пользователь = один профиль + цель + дневник.

Ключевая идея: **«цель — это число ккал, которое юзер сам выбрал». TDEE,
шаги и тренировки — отдельная информация для понимания расхода, но они НЕ
меняют план дня.** Запись 10 000 шагов не превращает «Поддержку» в
«Сушку». См. [docs/06-insights.md](docs/06-insights.md) и
[docs/FORMULAS.md](docs/FORMULAS.md).

## Стек

- **Backend:** Laravel 11 + Sanctum, PHP 8.3, PostgreSQL 16
- **Frontend:** Vue 3 (`<script setup>`) + Vite + TS strict + Pinia + Vue Router 4 + Tailwind v4
- **PWA:** vite-plugin-pwa + Workbox
- **Тесты:** Pest (бэк), Vitest (фронт). **Без тестов PR не принимается.**
- **Деплой:** Docker Compose + Caddy.

**НЕ используем:** Redis, очереди, push-уведомления, i18n, AI-чат, gamification, экспорт/импорт. См. полный список в [docs/README.md](docs/README.md).

## Прежде чем писать код — прочитай

В этом порядке:

1. [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — *что где живёт и кто что вызывает.*
2. [docs/FORMULAS.md](docs/FORMULAS.md) — *все числа в одном месте; источник правды для констант.*
3. [docs/06-insights.md](docs/06-insights.md) — *TDEE / mode / правила подсказок.*
4. [docs/02-database.md](docs/02-database.md) — *схема БД.*
5. [docs/03-api.md](docs/03-api.md) — *эндпоинты и контракты.*
6. [docs/04-frontend.md](docs/04-frontend.md) — *структура UI, компоненты, stores.*
7. [docs/05-design.md](docs/05-design.md) — *токены, темы, цвета.*
8. [docs/07-pwa-offline.md](docs/07-pwa-offline.md) — *оффлайн-очередь, idempotency.*
9. [docs/08-deployment.md](docs/08-deployment.md) — *деплой, бэкапы.*

Если в задаче упоминается «как это работает», «как считается» — иди сразу
в `FORMULAS.md`. Там для каждой цифры есть имя, значение, файл и причина
существования.

## Правила (must)

### Архитектурные
- **Никаких magic numbers в фичах.** Если в коде встречается число — у него
  должна быть именованная константа. См. полный реестр констант в
  [docs/FORMULAS.md](docs/FORMULAS.md).
- **Бэк ↔ фронт зеркала**: TDEE, ModeClassifier, Macros — одинаковые формулы,
  одинаковые числа. Меняешь одну сторону → меняй другую → синхронизируй
  тесты.
- **Слои фронта:** `lib/` — чистая логика (без Vue/HTTP), `api/` — fetch,
  `stores/` — Pinia state, `components/views` — UI. См.
  [ARCHITECTURE.md §Frontend layout](docs/ARCHITECTURE.md).
- **PSR-12, `declare(strict_types=1)`, FormRequest для валидации,
  Resource/Collection для ответов.**
- **TS strict, `any` запрещён** без явного `// TODO:`-комментария.
- **UUID v7 в публичных API**, BIGSERIAL внутри.
- **Денормализация на чтение:** `GET /days/{date}` отдаёт *всё* одним
  запросом. Не плодить отдельные эндпоинты для каждой коллекции дня.
- **Идемпотентность:** все POST/PUT/DELETE из фронта несут
  `Idempotency-Key`. Бэк хранит маппинг key→ресурс.

### Поведенческие
- **Тесты обязательны.** PR без них = не принимается. Backend → Pest, frontend → Vitest.
- **Vertical slices:** одна фича = DB → API → UI, единым PR. Не
  раскатываем по неделям.
- **Не выбрасывай данные тестов.** Если миграция меняет колонку — добавь
  явный `down()`/обратную миграцию.
- **Не вписывай числа в подсказки руками.** Если меняется порог —
  меняется константа правила, обновляется
  [docs/FORMULAS.md](docs/FORMULAS.md), обновляются тесты.

### Стилистические
- **Комментарии — только про *почему*, не про *что*.** «`// 1.8 г/кг —
  середина диапазона 1.6–2.2 г/кг»` — ОК. «`// сохраняем юзера»` поверх
  `User::create(...)` — нет.
- **Без эмодзи** ни в коде, ни в UI, ни в коммитах. Только если юзер прямо
  попросил.
- **Без новых .md файлов**, кроме как по явной просьбе. Сводки, планы,
  заметки — в conversation, не в файлы.

## Где править — типичные задачи

| Хочу... | Файл |
|---|---|
| ...поменять формулу TDEE | `backend/app/Services/Tdee/TdeeCalculator.php` + `frontend/src/lib/tdee.ts` + тесты |
| ...поменять что считается «На цели» | `backend/app/Services/Modes/ModeClassifier.php` + `frontend/src/lib/modes.ts` |
| ...поменять предлагаемые ккал по типу цели | `frontend/src/lib/modes.ts: GOAL_TYPE_DELTA` |
| ...добавить новую подсказку | новый класс в `backend/app/Services/Insights/Rules/` + регистрация в `InsightEngine::rules()` + тесты |
| ...поменять окно отображения подсказки | константы того же класса (`WINDOW_START_HOUR`, ...) |
| ...поменять что считается «достаточно данных для тренда веса» | `backend/app/Services/Stats/StatsAggregator.php: TREND_MIN_*` |
| ...поменять macro split (1.8 г/кг и 25%) | `backend/app/Support/Macros.php` + `frontend/src/lib/macros.ts` |
| ...добавить экран | `frontend/src/views/X.vue` + роут в `frontend/src/router/index.ts` |
| ...добавить эндпоинт | `routes/api.php` + контроллер + Request + Resource + сервис при необходимости + тест |

## Куда НЕ лезть без явной задачи

- В CI/CD — мы его пока не используем, не разводи `.github/workflows`.
- В Redis / очереди — не добавлять, пока юзер прямо не попросит.
- В i18n — UI только на русском.
- В новые UI-фреймворки — Tailwind v4 + Vue 3 уже есть, не тащи Vuetify/Element/др.

## Если в плане противоречие

- **Не выбирай молча**, спроси.
- В каждом большом PR держи список «что было неоднозначно и как я решил».

## Команды (через Makefile)

```
make up              поднять все контейнеры (dev)
make down            остановить
make logs            логи
make shell-backend   bash в backend контейнере
make shell-db        psql в postgres
make migrate         миграции
make test            backend pest
make deploy          git pull + build + up (на сервере)
```

Frontend:
```
cd frontend
npx vitest run                # все тесты
npx vue-tsc --noEmit          # type-check
npm run dev                   # vite dev сервер (если нужен без docker)
```
