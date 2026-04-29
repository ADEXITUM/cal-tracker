# 09. Roadmap

Порядок реализации по фазам. Внутри фазы — строго сверху вниз. Не переходить к следующей фазе пока текущая не закрыта (код + тесты + ручная проверка).

## Принципы

1. Vertical slices: фича = DB → API → UI end-to-end, не отдельными неделями
2. Тесты вместе с фичей, не «потом»
3. Каждая фаза заканчивается demo-able состоянием

---

## Phase 0 — Bootstrap

Цель: стек запускается, hello world в браузере.

- Init repo, README, .gitignore, структура каталогов
- Backend: `composer create-project laravel/laravel backend`, `php artisan install:api` (ставит Sanctum), `laravel/pint`
- Frontend: `npm create vite@latest frontend -- --template vue-ts`, install pinia/vue-router/tailwind/lucide-vue-next/vue3-apexcharts/vite-plugin-pwa/@fontsource-variable/inter+jetbrains-mono. TS strict
- Tailwind config с CSS variables (только light)
- docker-compose.yml + Caddyfile.dev + Dockerfile'ы (backend на serversideup/php, frontend multi-stage)
- `make up` → `http://localhost` показывает Vite welcome
- `make test` зелёный

**Готовность:** запуск работает, hello world видно.

---

## Phase 1 — Auth + Profile

Цель: регистрация, логин, профиль, пустой главный экран.

### Backend
- Миграции: users (расширить дефолт), profiles, goals
- Models + factories (`withProfile`, `withGoal` traits)
- Auth API: register, login, logout, me. Throttle middleware
- Profile API: GET, POST/PUT (upsert), TdeeCalculator service с unit tests
- Goals API: CRUD + GoalResolver + ModeClassifier с unit tests + автозакрытие предыдущего открытого

### Frontend
- UI primitives: AButton, AInput, ACard, APill
- Pinia setup, base API client с snake↔camel конвертацией
- Vue Router + global navigation guard (auth/guest/requiresProfile)
- LoginView, RegisterView + auth store. Токен в IndexedDB через idb-keyval
- ProfileSetupView — 3-шаговый wizard (пол+ДР, рост+вес, активность+первая цель)
- Empty DayView с заглушками секций

### Тесты
- Backend: 4 сценария (happy/auth/cross-user/validation) на каждый endpoint
- Frontend: auth store unit tests

**Готовность:** регистрация full-cycle. Зашёл → онбординг → главный экран. Вышел/зашёл → попал обратно.

---

## Phase 2 — Day View Core

Цель: главный экран с реальными данными. Можно добавить приём/замер/тренировку. Свайп между днями.

### Backend
- Миграции: day_entries, dishes, measurements, meals, workouts. Eloquent events (usage_count, денорм user_id, snapshot КБЖУ в meal)
- Days API: GET /days/{date} (главный — отдаёт ВСЁ), PUT /days/{date}, GET /days?from=&to=. DayAggregator service
- Meals API: POST с idempotency, PUT, DELETE. MealFactory service
- Measurements API
- Workouts API
- Dishes API: GET с search, CRUD, soft delete

### Frontend
- TS типы из API (camelCase)
- Day store (full реализация с optimistic updates + rollback)
- KcalRing (SVG-кольцо с lerp)
- AModeBadge с тестами
- Day-секции: DayKcalCard, DayMacrosCards, MealsList/MealItem, MeasurementsCard, WorkoutsCard, WellbeingCard
- ASheet базовый компонент (swipe-down to close, spring анимация)
- AddMealSheet с DishPicker + ANumpad
- AddMeasurementSheet, AddWorkoutSheet
- DayFab с radial menu
- useSwipe composable + slide-анимация между днями
- DishesView (список + поиск + CRUD)

### Тесты
- Backend: GetDayTest, CreateMealTest с idempotency, snapshot dish
- Frontend: useSwipe, ModeBadge component

**Готовность:** утро → весы → ввёл → видно. Поел → добавил → кольцо обновилось. Свайп влево → вчера → видишь данные.

---

## Phase 3 — Insights + Stats

Цель: умные подсказки на главной + экран графиков.

### Backend
- InsightEngine + 7 правил (см. 06-insights.md)
- Insights включаются в DayAggregator
- Stats API: summary + series с rolling 7-day average

### Frontend
- DayInsights component с borderLeft accent + dismiss свайпом
- Mode explainer modal (тап на AModeBadge)
- StatsView с 4 табами (Вес/КБЖУ/Состав/Активность)
- ASegmented period selector
- ApexCharts: WeightChart, BodyFatChart, MacroBars
- ComparisonCard "было → стало"
- HistoryView — heatmap календарь с переходом на /day/{date}
- GoalsView — список + создание с live preview режима
- TS-зеркала TdeeCalculator/ModeClassifier в `lib/tdee.ts`, `lib/modes.ts`

### Тесты
- Backend: unit на каждый Insight (ветки tone)
- Frontend: tdee/modes lib (одинаковые числа что и backend)

**Готовность:** на главной "Идёшь по плану". В Stats график веса с трендом. В Goals можно изменить цель — режим обновляется.

---

## Phase 4 — PWA + Offline + Multi-account

Цель: устанавливается как PWA. Работает оффлайн. Multi-account.

### Frontend
- PWA manifest + иконки (192/512/maskable + apple-touch + iOS meta-tags)
- Service Worker config через vite-plugin-pwa (strategies из 07-pwa-offline.md)
- IDB кэш последних 7 дней (read-through pattern)
- Offline queue с retry + idempotency + UI индикатор
- Multi-account через savedAccounts в auth store + IDB. Account switcher в SettingsView
- Splash screens iOS (опц.)
- Lighthouse PWA score >= 90

### Тесты
- Frontend: queue logic unit, auth store с разными аккаунтами

**Готовность:** установил иконку. Самолёт → видно последний день. Записал приём → после посадки синк. Можно добавить второй аккаунт.

---

## Phase 5 — Dark theme + Polish

Цель: тёмная тема. Анимации плавные. Мелкие баги.

- Dark theme variables + auto через `prefers-color-scheme` + override через Settings
- useTheme composable, theme picker в Settings (Auto/Light/Dark)
- Number lerp везде где числа
- Page transitions, mount fade-up для карточек
- prefers-reduced-motion respect
- Long press на meal → action menu
- Swipe-to-delete на карточках
- Pull-to-refresh
- Haptics на iOS PWA через navigator.vibrate
- Все error states + empty states + skeleton states
- Toast система (success/error/info, auto-hide 3s)
- Profile editing в Settings с live update TDEE

**Готовность:** ночью → тёмная. Любое действие даёт feedback. Ничего не прыгает.

---

## Phase 6 — Production Deploy

Цель: продакшн на VPS, бэкапы крутятся.

- docker-compose.yml финальный + db-backup container + Caddyfile (по IP пока)
- Secrets: secrets/db_password.txt
- deploy.sh + Makefile тесты
- Деплой на VPS по инструкции из 08-deployment.md
- Дождаться первого автобэкапа → тест восстановления
- README на GitHub с инструкциями: установка на свой VPS, бэкапы

**Готовность:** прод живёт. Регистрация работает. Утром в `./backups/daily/` свежий файл.

---

## Post-MVP (по приоритету)

1. Domain + HTTPS (час работы)
2. Photos в meals/measurements + thumbnails в backend volume
3. Сравнение before/after с swipe slider
4. Импорт/экспорт CSV/JSON
5. Off-site backup на B2/S3 через restic
6. Smart insights: плато, корреляции (sleep ↔ overage), цикл (для девушки), diet break reminder
7. Apple Health / Google Fit интеграция (через export → import)
8. Push-уведомления (iOS 16.4+)
9. i18n
10. Полноценная база упражнений

## Метрики готовности фазы

- [ ] Все задачи закрыты (commits в main)
- [ ] Тесты зелёные (`make test`)
- [ ] Lint без ошибок
- [ ] Type-check без ошибок
- [ ] Smoke test ключевых сценариев пройден
- [ ] Документация обновлена при изменении контракта

## Оценка времени

| Phase | Дней full-time |
|---|---|
| 0 — Bootstrap | 1 |
| 1 — Auth + Profile | 3-4 |
| 2 — Day View Core | 4-5 |
| 3 — Insights + Stats | 3-4 |
| 4 — PWA + Offline + Multi-account | 3-4 |
| 5 — Polish + Dark | 2-3 |
| 6 — Production Deploy | 1-2 |
| **MVP** | **17-23 дня full-time** |

Вечерами/выходными: 5-7 недель календарных. По 1-2ч/день: 2-3 месяца.

## Open questions

- GitHub Actions с Phase 0 — спросить владельца. Рекомендую да (+30 минут к Phase 0)
