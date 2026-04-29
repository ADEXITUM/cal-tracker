# 03. API

Префикс `/api/v1`. Bearer-токены Sanctum. Идентификаторы в URL — UUID, не autoincrement. Даты дней `YYYY-MM-DD`, timestamps ISO 8601 с TZ.

## Формат ответа

- Single: `{"data": {...}}`
- Collection: `{"data": [...], "meta": {"next_cursor": "uuid|null", "count": N}}`
- Validation 422: стандартный Laravel `{message, errors: {field: [...]}}`
- 401: `{message: "Unauthenticated"}`. 404: `{message: "Resource not found"}`

snake_case на бэке, фронт конвертирует в camelCase в API-клиенте.

## Конвенции

- Курсорная пагинация: `?cursor=<uuid>&limit=20`
- POST с idempotency: header `Idempotency-Key`, при повторе возвращает существующий результат
- CSRF не нужен (Bearer, не cookies)
- Throttle: 60/min guest, 120/min auth (Laravel default), отдельно жёстче на регистрацию (5/час по IP) и логин (10/min по IP, 5/min по email)

## Endpoints

### Auth

- `POST /auth/register` — `{email, password (min:8), name, device_name}` → `{user, token}`. **Не различать "нет email" и "неверный пароль"** в ответах.
- `POST /auth/login` — `{email, password, device_name}` → `{user, token}`
- `POST /auth/logout` — удаляет текущий токен → 204
- `GET /auth/me` → `{user, current_goal}`

### Profile

- `GET /profile` → 404 если нет, иначе данные + computed `tdee_kcal`
- `PUT /profile` — upsert (создаёт если нет)

Validation: `gender in [male,female]`, `birth_date before today after 1900`, `height_cm 100..250`, `activity_level in [sedentary,light,moderate,active]`.

### Goals

- `GET /goals?from=&to=` — список, sort by start_date DESC
- `POST /goals` — при создании с `end_date=null` автоматически закрывает предыдущий открытый
- `PUT /goals/{uuid}`, `DELETE /goals/{uuid}`

Validation: `kcal 800..6000`, `protein 0..500`, `fat 0..400`, `carbs 0..1000`, `end_date >= start_date`.

### Days (главный endpoint)

**`GET /days/{date}`** — возвращает ВСЁ для главного экрана:

```
{
  date,
  day_entry: {...} | null,
  goal: {kcal, protein_g, fat_g, carbs_g, ...},
  tdee: {bmr, activity_kcal, total},
  mode: {code, label, delta_kcal},
  totals: {kcal, protein_g, fat_g, carbs_g},
  meals: [...],
  measurements: [...],
  workouts: [...],
  insights: [{code, tone, title, body}]
}
```

Если day_entry для даты нет — возвращает `day_entry: null`, остальные computed-поля заполняет (goal, tdee, mode, пустые totals). Не 404.

`mode.code` ∈ `extreme_cut | cut | cut_lite | maintenance | light_bulk | bulk` (см. 06-insights).

**`PUT /days/{date}`** — partial update day_entry (создаёт если нет): `mood`, `wellbeing`, `sleep_hours`, `steps`, `notes`.

**`GET /days?from=&to=`** — список агрегатов для календаря (без полных meals): `{date, totals, weight_kg, mode_code, delta_from_goal}`.

### Meals / Measurements / Workouts

- `POST /days/{date}/{meals|measurements|workouts}` — поддерживают `Idempotency-Key`
- `PUT /{meals|measurements|workouts}/{uuid}`, `DELETE /...`

**Meals:** либо `{slot, eaten_at, dish_uuid, grams}` (бэк сам считает КБЖУ как `dish.X_per_100g * grams / 100` и сохраняет snapshot), либо ad-hoc `{slot, eaten_at, name, kcal, protein_g, fat_g, carbs_g}`.

**Measurements:** только `weight_kg` обязателен, остальное nullable.

**Workouts:** `{name, duration_min?, kcal_burned?, notes?}`.

### Dishes

- `GET /dishes?search=&limit=20` — sort: `usage_count DESC, name`. Поиск `ILIKE %query%`.
- `POST /dishes`, `PUT /dishes/{uuid}`, `DELETE /dishes/{uuid}` (soft delete)

### Stats

- `GET /stats/summary?from=&to=` → агрегаты периода: weight (start/end/delta/trend_kg_per_week), body_fat_pct, kcal (avg, vs_goal, days_tracked), deficit_avg, active_days_pct
- `GET /stats/series?metric=&from=&to=` → `{metric, points: [{date, value}], rolling_avg_7d: [...]}`. Metric ∈ weight, body_fat_pct, muscle_mass_kg, body_water_pct, kcal, protein_g, fat_g, carbs_g, steps

## Сервисы (Laravel)

Контроллеры тонкие. Логика в сервисах:

- `Tdee\TdeeCalculator::compute(Profile, ?int $steps, Collection $workouts, float $latestWeight): TdeeBreakdown`
- `Goals\GoalResolver::forDate(User, Carbon): ?Goal`
- `Modes\ModeClassifier::classify(int $goalKcal, int $tdeeKcal): Mode`
- `Days\DayAggregator` — собирает full day для GET /days/{date}
- `Insights\InsightEngine` — генерирует массив insights
- `Meals\MealFactory` — создание meal из dish или ad-hoc, расчёт snapshot

## Open questions

- Bulk-импорт endpoint — не делаем в MVP, оффлайн-синхронизация работает по одному запросу с idempotency
