# ARCHITECTURE — что где живёт и кто что вызывает

> Карта репозитория «один экран». Если меняешь архитектуру — поправь и тут.

## Топология

```
diet-tracker/
├── backend/          Laravel 11 + Sanctum + PostgreSQL
├── frontend/         Vue 3 + Vite + TS + Pinia + Tailwind v4
├── docs/             docs (этот файл здесь)
├── docker-compose.yml + Caddyfile + deploy.sh + Makefile
└── AGENTS.md / CLAUDE.md  агенты-входные-двери
```

Всё ходит через Caddy: фронт раздаётся как PWA, API проксируется на бэк.

---

## Backend layout (`backend/app/`)

```
Http/
  Controllers/Api/V1/      тонкие контроллеры — валидация → сервис → ресурс
  Requests/Api/            FormRequest правила (валидация полей)
  Resources/               JSON-форматирование ответов
Models/                    Eloquent — UUID public + BIGSERIAL внутри
Services/                  бизнес-логика; контроллеры её вызывают
  Days/DayAggregator        собирает «день» одним запросом
  Goals/GoalResolver        ищет активную цель на дату
  Insights/                 правила подсказок (см. 06-insights.md)
    InsightEngine           прогоняет все правила, сортирует, отдаёт топ-2
    Rules/*                 сами правила, по одному на класс
  Meals/MealFactory         меняет dish-нутриенты в meal-нутриенты
  Modes/ModeClassifier      классификация дня (см. FORMULAS.md §4)
  Stats/StatsAggregator     summaries + series для /stats
  Tdee/TdeeCalculator       BMR + base + steps + workouts (см. FORMULAS.md §3)
Support/                    плоский слой констант — импортится отовсюду
  Numbers.php               kcal/g, days/week, sec/day
  Macros.php                сплит белков/жиров/углеводов
Providers/
```

### Поток одного запроса (пример: `GET /api/v1/days/2026-04-30`)

```
HTTP
 ├── route /api/v1/days/{date}        routes/api.php
 ├── DayController@show               валидирует, тянет user
 ├── DayAggregator::forDate           собирает: entry, goal, tdee, totals, mode, insights
 │     ├── GoalResolver               активная цель на дату
 │     ├── TdeeCalculator             BMR + Base + Steps + Workouts
 │     ├── ModeClassifier             eaten vs goal → mode
 │     └── InsightEngine + Rules/*    → insights[]
 └── DayResource → JSON               snake_case в БД, snake_case в API
```

Фронт получает JSON, прогоняет через `client.ts: camelizeResponse`, дальше
работает в camelCase.

---

## Frontend layout (`frontend/src/`)

```
api/                       тонкие обёртки fetch + camelize
  client.ts                 базовый request; токен подставляется из стора auth
  days.ts, goals.ts, ...    API-ресурсы по доменам
lib/                       чистая логика (без Vue/UI)
  time.ts                   единицы времени, helpers
  tdee.ts                   зеркало бэкового TDEE
  modes.ts                  зеркало бэкового ModeClassifier + GoalType helpers
  macros.ts                 сплит БЖУ
  dayCache.ts               IndexedDB кэш дня
  __tests__/                Vitest для всего lib/
stores/                    Pinia store; единственное место для state
  auth.ts                   токены, мульти-аккаунт
  day.ts                    текущий день
  goals.ts, dishes.ts ...
composables/               useSwipe, useTheme, useOfflineQueue, ...
components/                переиспользуемые UI-куски
  ui/                       AButton, ACard, ASegmented, AModeBadge, ...
  charts/                   KcalRing, LineChart, BarChart, DayHeatmap
  day/                      компоненты экрана /day
  goals/                    редактор/пресеты цели
  add/                      нижние шиты «добавить ...»
views/                     роуты (1 view = 1 URL)
router/                    vue-router; auth/profile guards
types/                     ровно копия API-моделей в TS
```

Правила слоёв:

- **lib/** не импортит ничего, кроме `types/` и других `lib/*`. Никаких Vue, fetch, IDB.
- **stores/** не импортит компоненты. Импортит api + lib.
- **components/** не лезут в api напрямую. Только через store.
- **views/** не лезут в api напрямую. Только через store.

Если задумываешься «куда положить» — задай вопрос: «может ли это работать
вне браузера?» Если да → `lib/`. Если оно делает HTTP → `api/`. Если
держит state → `stores/`.

---

## Поток одного клика (пример: «добавить приём»)

```
User тапает FAB ▶ AddMealSheet (component)
 ├── читает dishes из useDishesStore
 ├── on submit → useDayStore.addMeal({...})
 │    └── daysApi.addMeal(date, payload)
 │        └── POST /api/v1/days/{date}/meals  (Idempotency-Key)
 │            ├── MealRequest валидация
 │            ├── MealController@store
 │            │    └── MealFactory::fromDish или ::fromAdHoc
 │            └── MealResource → JSON
 └── после ответа: useDayStore.fetch() — обновляем день целиком
```

Идемпотентность: фронт генерит `Idempotency-Key`, бэк хранит маппинг key→meal_id, второй такой же запрос возвращает тот же ресурс. Делает оффлайн-очередь безопасной.

---

## Пути синхронизации (бэк ↔ фронт)

Каждое из этих чисел/правил **зеркалируется** на обеих сторонах. При изменении меняй одновременно — иначе цифры в превью при создании цели разойдутся с тем, что бэк сохранит.

| Что | Бэк | Фронт |
|---|---|---|
| TDEE формула | `TdeeCalculator` | `lib/tdee.ts` |
| Mode классификация | `ModeClassifier` | `lib/modes.ts: classifyMode` |
| Macro split | `Support/Macros.php` | `lib/macros.ts: defaultMacroSplit` |
| Atwater (4/4/9) | `Support/Numbers.php` | `lib/macros.ts` |

Тесты-зеркала:
- `tests/Unit/TdeeCalculatorTest.php` ↔ `lib/__tests__/tdee.spec.ts`
- `tests/Unit/ModeClassifierTest.php` ↔ `lib/__tests__/modes.spec.ts`

---

## База данных — ключевые таблицы

См. полную схему в [docs/02-database.md](02-database.md). Сводка:

```
users           ─┬─ profiles            (gender, height, birth_date — без activity_level)
                 ├─ goals               (kcal/p/f/c/type/start_date/end_date)
                 ├─ measurements        (weight/body_fat/обхваты, по дате)
                 ├─ dishes              (kcal/p/f/c per_100g)
                 └─ day_entries         (1 запись на дату)
                       ├─ meals
                       ├─ measurements (см. выше)
                       └─ workouts (kcal_burned)
```

Денормализация на чтение: `GET /days/{date}` отдаёт всё одним запросом. `DayAggregator` агрегирует.

---

## PWA

- `vite-plugin-pwa` + Workbox в `frontend/vite.config.ts`.
- Оффлайн-очередь — `composables/useOfflineQueue.ts` + IndexedDB.
- Все мутации (POST/PUT/DELETE) проходят через очередь и используют `Idempotency-Key`.
- См. [docs/07-pwa-offline.md](07-pwa-offline.md).

---

## Тесты

| Где | Стек | Запуск |
|---|---|---|
| Backend Feature | Pest | `make test` (или `php artisan test` внутри контейнера) |
| Backend Unit | Pest | то же |
| Frontend | Vitest + Vue Test Utils | `cd frontend && npx vitest run` |

Без тестов PR не принимаем (правило из [README в docs/](README.md)).

---

## Деплой

Docker Compose поднимает: backend (PHP-FPM), frontend (статика после билда), Caddy, Postgres, postgres-backup-local sidecar. Подробности в [docs/08-deployment.md](08-deployment.md).
