# 06. Insights, режимы и TDEE

> **Все цифры из этого документа — это копия из кода.** Если код и документ
> расходятся, прав код. Источник правды для каждого числа указан рядом
> (имя класса + константа). Если нужно поменять порог — меняй константу,
> потом обнови этот документ.

## Принципы

1. **Цель = сколько ккал есть в день.** Это число фиксируется при создании
   цели и никаким TDEE не «корректируется». Шаги/тренировки лишь меняют
   расход, а не план.
2. **Бейдж дня = «как съел vs план»**, а не «как съел vs TDEE». Запись шагов
   не должна менять «Поддержка» на «Сушка».
3. **Тип цели (cut / maintenance / bulk)** — отдельная сущность. Висит на
   цели весь её срок и используется только для долгосрочных подсказок (тренд
   веса).
4. **Минимум подсказок:** показываем не больше 2 на главном экране, выбор по
   priority DESC.

## Тоны

`neutral` (серый), `good` (зелёный), `warm` (accent). `warn`/`alert` зарезервированы, в MVP не используются.

## TDEE — расчёт расхода

Источник: `App\Services\Tdee\TdeeCalculator`. Зеркало во фронте: `frontend/src/lib/tdee.ts`. Тесты на обеих сторонах сверяют результат.

```
BMR (Mifflin-St Jeor):
  male:   10·weight + 6.25·height − 5·age + 5
  female: 10·weight + 6.25·height − 5·age − 161

Base   = BMR × BASE_MULTIPLIER          // 1.2 — sedentary baseline
Steps  = steps × weight × STEP_KCAL_PER_KG   // 0.0005 — kcal per (step × kg)
Workouts = sum(workout.kcal_burned)

TDEE = Base + Steps + Workouts
```

Константы (один источник правды):

| Имя | Значение | Где |
|---|---|---|
| `BASE_MULTIPLIER` | 1.2 | `TdeeCalculator::BASE_MULTIPLIER`, `frontend/src/lib/tdee.ts` |
| `STEP_KCAL_PER_KG` | 0.0005 | `TdeeCalculator::STEP_KCAL_PER_KG`, `frontend/src/lib/tdee.ts` |

**Activity level** (`sedentary` / `light` / `moderate` / `active`) больше **не хранится в профиле**. Используется только локально, в калькуляторе цели, чтобы предложить стартовое число kcal — см. `frontend/src/lib/tdee.ts: ACTIVITY_MULTIPLIER`.

## Классификация дня (mode)

Источник: `App\Services\Modes\ModeClassifier::classify($goalKcal, $eatenKcal)`.
Зеркало: `frontend/src/lib/modes.ts: classifyMode`.

```
delta = eaten − goal
pct   = |delta| / goal

|pct| ≤ 5%       → on_target  «На цели»
delta > 0, ≤ 15% → over       «Перебор»
delta > 0, > 15% → far_over   «Сильный перебор»
delta < 0, ≤ 15% → under      «Недобор»
delta < 0, > 15% → far_under  «Сильный недобор»
```

| Имя | Значение | Где |
|---|---|---|
| `ON_TARGET_PCT` | 0.05 | `ModeClassifier::ON_TARGET_PCT`, `frontend/src/lib/modes.ts` |
| `MODERATE_PCT`  | 0.15 | `ModeClassifier::MODERATE_PCT`, `frontend/src/lib/modes.ts` |

## Тип цели (GoalType)

Хранится в `goals.type`: `cut` | `maintenance` | `bulk`. Не вычисляется. Влияет только на:

1. Заголовок чипа на /day («Сушка · день 5/30»).
2. Ассессмент тренда веса (см. ниже).
3. Предложение калорий при создании цели:
   - `cut`        → average_TDEE − 400
   - `maintenance` → average_TDEE
   - `bulk`        → average_TDEE + 300

Источник: `frontend/src/lib/modes.ts: GOAL_TYPE_DELTA`.

## Macro split

Источник: `App\Support\Macros::defaultSplit`. Зеркало: `frontend/src/lib/macros.ts: defaultMacroSplit`.

```
protein_g = round(weight × 1.8)            // PROTEIN_G_PER_KG
fat_kcal  = target_kcal × 0.25             // FAT_RATIO
fat_g     = round(fat_kcal / 9)            // KCAL_PER_FAT_G
carbs_g   = round((target_kcal − fat_kcal − protein_g × 4) / 4)
                                            // KCAL_PER_PROTEIN_G / KCAL_PER_CARB_G
```

| Имя | Значение | Где |
|---|---|---|
| `PROTEIN_G_PER_KG`  | 1.8  | `Support\Macros::PROTEIN_G_PER_KG`, `lib/macros.ts: DEFAULT_PROTEIN_G_PER_KG` |
| `FAT_RATIO`         | 0.25 | `Support\Macros::FAT_RATIO`, `lib/macros.ts: DEFAULT_FAT_RATIO` |
| `KCAL_PER_PROTEIN_G`| 4    | `Support\Numbers::KCAL_PER_PROTEIN_G`, `lib/macros.ts: KCAL_PER_PROTEIN_G` |
| `KCAL_PER_CARB_G`   | 4    | `Support\Numbers::KCAL_PER_CARB_G`, `lib/macros.ts: KCAL_PER_CARB_G` |
| `KCAL_PER_FAT_G`    | 9    | `Support\Numbers::KCAL_PER_FAT_G`, `lib/macros.ts: KCAL_PER_FAT_G` |

## InsightEngine

Service. Каждое правило реализует `InsightInterface { evaluate(InsightContext): ?Insight; priority(): int }`. Engine вызывает все, фильтрует non-null, сортирует по priority DESC, возвращает топ-2.

`InsightContext` несёт: `User`, `Carbon $date`, `?DayEntry`, `?Goal`, `?TdeeBreakdown`, `?Mode`, `array $totals`, `Collection $meals/measurements/workouts`, `int $hoursIntoDay`.

## Правила (актуальные пороги)

Каждое значение — `class CONST` в соответствующем правиле; ниже — таблица.

### EmptyDayInsight — priority 90
Условие: `meals.empty && measurements.empty`.
- Today: «Запиши вес или приём чтобы отслеживать день»
- Past: «Не было замеров. Запиши хотя бы вес — тренд важнее идеала»

### RecoveryAfterOverateInsight — priority 85
Утренняя подсказка после перебора.
- `hoursIntoDay ≤ MORNING_CUTOFF_HOUR` (12)
- Вчерашний `eaten − goal > EXCESS_THRESHOLD_KCAL` (300)

### EndOfDayDeficitInsight — priority 80
Конец дня.
- `hoursIntoDay ≥ END_OF_DAY_HOUR` (22)
- `|diff| ≤ ON_TRACK_KCAL_BAND` (200) → «По плану» (good)
- `diff > 200` → «Перебор. Это (diff × 30) ккал к месяцу» (warm)
- `diff < −200` → «Большой дефицит» (neutral)

### ForecastInsight — priority 70
Прогноз остатка дня.
- `WINDOW_START_HOUR..WINDOW_END_HOUR` = 14..22
- `meals.count ≥ MIN_MEALS` (2)
- `avgMealKcal` = среднее за `AVG_MEAL_LOOKBACK_DAYS` (30) дней
- `remaining = TYPICAL_MEALS_PER_DAY (4) − meals.count`
- `forecast = totals + remaining × avgMealKcal`
- `|forecast − goal| ≤ ON_PLAN_BAND` (100) → good
- `forecast − goal > 100` → warm
- `forecast − goal < −FAR_UNDER_BAND` (200) → warm

### KcalRemainingInsight — priority 60
- `WINDOW_START_HOUR..WINDOW_END_HOUR` = 10..22
- `totals.kcal < goal.kcal`

### OnlyBreakfastInsight — priority 50
- `hoursIntoDay ≥ REMINDER_AFTER_HOUR` (13), все приёмы — `slot = breakfast`.

### WeightTrendInsight — priority 30
Долгосрочный тренд через линейную регрессию.
- `goalAge ≥ MIN_GOAL_AGE_DAYS` (14)
- Замеров за `LOOKBACK_DAYS` (30) дней ≥ `MIN_MEASUREMENTS` (5)
- Регрессия по последним `REGRESSION_WINDOW` (14) точкам, `slope × 7 = kgPerWeek`

| Тип цели | Порог | Текст |
|---|---|---|
| cut | `kgPerWeek ≤ −1.0` | Быстрее безопасного |
| cut | `kgPerWeek ≤ −0.5` | Безопасная скорость |
| cut | `kgPerWeek ≥ −0.2` | Прогресс замедлился |
| bulk | `kgPerWeek ≥ 0.7` | Слишком быстро |
| bulk | `0.25 ≤ kgPerWeek ≤ 0.5` | Lean bulk темп |

## Stats — тренд веса в карточке /stats

`App\Services\Stats\StatsAggregator::weightSummary`. Линейная регрессия по дневным значениям веса.

Тренд показывается **только если**:
- замеров ≥ `TREND_MIN_MEASUREMENTS` (5)
- размах ≥ `TREND_MIN_SPAN_DAYS` (7) дней

Иначе `trend_kg_per_week = null` и UI показывает «—». Это защищает от артефакта вроде «−140 кг/нед» при 2 точках.

Сглаживание графиков: rolling average окно `ROLLING_WINDOW_DAYS` (7).

## Mode explainer modal

Открывается тапом на AModeBadge. Содержит:
1. Заголовок режима
2. Расчёт: «съедено / план / разница»
3. Текст из словаря `MODE_DESCRIPTIONS`
4. CTA «Изменить цель» → /goals

Расчёт прогноза по неделям убран — он живёт в долгосрочных insights, не в модалке дня.

## Тесты

- `backend/tests/Unit/TdeeCalculatorTest.php` — формула BMR, base, steps, workouts.
- `backend/tests/Unit/ModeClassifierTest.php` — все 5 веток + границы.
- `frontend/src/lib/__tests__/{tdee,modes}.spec.ts` — зеркальные тесты, должны давать те же числа.

## Open questions

- Dismissed insights живут только на фронте (`localStorage[dismissed_insights_${date}]`) — синк между устройствами не нужен.
