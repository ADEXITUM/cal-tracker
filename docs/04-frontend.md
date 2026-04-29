# 04. Frontend

## Структура

```
frontend/src/
├── api/           # client.ts + per-resource modules
├── stores/        # Pinia: auth, day, dishes, settings
├── composables/   # useDay, useFormatters, useSwipe, useHaptics, useOfflineQueue, useTheme
├── lib/           # idb, tdee, modes, dates — pure functions, mirror of backend
├── components/
│   ├── ui/        # AButton, ASheet, AInput, ANumpad, ASegmented, ACard, APill, AAvatar, ASkeleton, AModeBadge
│   ├── charts/    # KcalRing, WeightChart, BodyFatChart, MacroBars, DayHeatmap
│   ├── day/       # DayHeader, DayKcalCard, DayMacrosCards, DayInsights, MealsList, MealItem, MeasurementsCard, WorkoutsCard, WellbeingCard, DayFab
│   ├── add/       # AddMealSheet, AddMeasurementSheet, AddWorkoutSheet, DishPicker, DishCreateSheet
│   └── stats/     # StatsTabs, PeriodSelector, ComparisonCard
├── views/         # Login, Register, ProfileSetup, Day, History, Stats, Goals, Dishes, Settings
├── router/
└── styles/
```

## API клиент

Один экземпляр `ApiClient` (singleton), импортируется во все `api/*.ts`. Делает:

- Подставляет Bearer-токен из auth store
- Конвертирует snake_case → camelCase в response, обратно в request
- При 401 — вызывает `onUnauthorized` (logout + redirect)
- При 422 — бросает `ValidationError` с `errors` объектом
- Поддерживает `idempotencyKey` опцию (добавляет header)

## Pinia stores

### auth
State: `currentUser: User | null`, `savedAccounts: SavedAccount[]` (синкаются из IndexedDB при старте). Каждый saved account = `{uuid, email, name, avatarColor, token, lastUsedAt}`.

Actions: `login`, `register`, `logout`, `switchTo(uuid)`, `removeAccount(uuid)`, `restoreFromIdb()`.

### day
State: `currentDate: string`, `data: DayResource | null`, `loading`, `error`.

Actions: `setDate(date)`, `fetch()`, `addMeal/addMeasurement/addWorkout/updateDayEntry` (optimistic с rollback при ошибке), `goToYesterday/Tomorrow/Today`.

### dishes
State: `items: Dish[]` (кэш в памяти + IDB), TTL 5 минут.

Actions: `fetchAll`, `search(query)` (локальная фильтрация), `create/update/delete`.

### settings
State: `themeMode: 'auto' | 'light' | 'dark'`, `weekStartsOn: 0|1` (default 1). Persist в localStorage.

## Composables

- `useDay()` — реактивно подгружает день, экспонирует computed (totals, mode, meals и т.д.)
- `useFormatters()` — `formatKcal` (с тонким пробелом), `formatWeight`, `formatPercent`, `formatMacro`, `formatDuration`, `formatRelativeDate` ("Сегодня"/"Вчера"/"29 апр")
- `useSwipe({onLeft, onRight, threshold})` — для DayView навигации между днями
- `useHaptics()` — `tap`, `success`, `warning` через `navigator.vibrate`, тихо деградирует
- `useOfflineQueue()` — см. 07-pwa-offline.md
- `useTheme()` — читает настройку, накладывает `data-theme` на html, слушает `prefers-color-scheme`

## Routing

```
/login, /register             — meta: { guest: true }
/profile/setup                — meta: { auth: true, requiresProfile: false }
/day/:date?                   — meta: { auth: true }
/history                      — meta: { auth: true }
/stats                        — meta: { auth: true }
/dishes, /goals, /settings    — meta: { auth: true }
```

Глобальный guard:
- `meta.auth` без юзера → /login
- `meta.guest` с юзером → /
- `requiresProfile !== false` и `user.has_profile === false` → /profile/setup

## TS типы

Все типы — в `src/types/api.ts`, отражают API ответы (camelCase). Ключевые: `User`, `Profile`, `Goal`, `DayResource`, `Meal`, `Measurement`, `Workout`, `Dish`, `Insight`, `ModeCode`, `TdeeBreakdown`. Агент генерирует их из 02-database.md и 03-api.md.

## Состояния загрузки

Везде три состояния: loading (skeleton с пульсом, **не спиннер**) / error (карточка с retry) / success.

## Mobile-first

Базовый viewport 380px. Breakpoints Tailwind. На desktop max-width 480px по центру (приложение остаётся «телефонным»). Bottom navigation на мобиле, sidebar на desktop.

## Open questions

- Optimistic updates с rollback — реализовать через draft-pattern в day store: применить → запрос → при ошибке откатить
