# FORMULAS — все числа в одном месте

> **Это страница-индекс.** Каждая формула живёт в коде как именованная
> константа; здесь — таблица: что значит, где определено, с чем сравнивается.
> Если в коде появляется число, которого нет в этой таблице — это баг.

Принцип: **никаких magic numbers в фичах.** В feature-коде встречаются только
имена констант (`STEP_KCAL_PER_KG`, `ON_TARGET_PCT` и т. д.). Сами числа
живут в одном из этих файлов:

**Backend (PHP):**
- `app/Support/Numbers.php` — фундаментальные постоянные (kcal/g, дни/неделя)
- `app/Support/Macros.php` — split белков/жиров/углеводов
- `app/Services/Tdee/TdeeCalculator.php` — TDEE формула
- `app/Services/Modes/ModeClassifier.php` — классификация дня
- `app/Services/Insights/Rules/*.php` — пороги конкретных подсказок
- `app/Services/Stats/StatsAggregator.php` — пороги для трендов

**Frontend (TS):**
- `src/lib/time.ts` — единицы времени
- `src/lib/macros.ts` — Atwater + split
- `src/lib/tdee.ts` — TDEE (зеркало бэка)
- `src/lib/modes.ts` — режим дня + GoalType (зеркало бэка)

Бэк и фронт **должны давать одинаковые числа** на одинаковых входах. Это
покрыто параллельными тестами.

---

## 1. Энергия и масса

### 1.1 Atwater factors — kcal на грамм макронутриента

| Константа | Значение | Бэк | Фронт |
|---|---:|---|---|
| `KCAL_PER_PROTEIN_G` | 4 | `Numbers::KCAL_PER_PROTEIN_G` | `lib/macros.ts: KCAL_PER_PROTEIN_G` |
| `KCAL_PER_CARB_G`    | 4 | `Numbers::KCAL_PER_CARB_G`    | `lib/macros.ts: KCAL_PER_CARB_G` |
| `KCAL_PER_FAT_G`     | 9 | `Numbers::KCAL_PER_FAT_G`     | `lib/macros.ts: KCAL_PER_FAT_G` |

Используются в:
- расчёте калорий блюда из макросов;
- обратном расчёте (target_kcal → граммы) при построении дефолт-сплита.

### 1.2 Per-100g basis для блюд

| Константа | Значение | Бэк |
|---|---:|---|
| `NUTRITION_REFERENCE_GRAMS` | 100 | `Numbers::NUTRITION_REFERENCE_GRAMS` |

Поля dish'а — `kcal_per_100g`, `protein_per_100g`, и т. д. Когда блюдо
кладётся в meal, нутриенты масштабируются: `factor = grams / 100`.

---

## 2. Время

| Константа | Значение | Бэк | Фронт |
|---|---:|---|---|
| `SECONDS_PER_DAY` | 86 400  | `Numbers::SECONDS_PER_DAY` | `lib/time.ts: SECONDS_PER_DAY` |
| `MS_PER_DAY`      | 86 400 000 | — | `lib/time.ts: MS_PER_DAY` |
| `DAYS_PER_WEEK`   | 7  | `Numbers::DAYS_PER_WEEK` | `lib/time.ts: DAYS_PER_WEEK` |
| `DAYS_PER_MONTH`  | 30 | `Numbers::DAYS_PER_MONTH` | — |

`DAYS_PER_MONTH = 30` — округление для текста подсказок («перебор × 30 дней
= ккал к месяцу»). Не используется как единица измерения времени.

---

## 3. TDEE (расход энергии)

### 3.1 Базовый расход

```
BMR (Mifflin-St Jeor):
  male:   10·weight + 6.25·height − 5·age + 5
  female: 10·weight + 6.25·height − 5·age − 161

Base   = round(BMR × BASE_MULTIPLIER)
Steps  = round(steps × weight × STEP_KCAL_PER_KG)
Workouts = sum(workout.kcal_burned)

TDEE = Base + Steps + Workouts
```

| Константа | Значение | Бэк | Фронт |
|---|---:|---|---|
| `BASE_MULTIPLIER`    | 1.2    | `TdeeCalculator::BASE_MULTIPLIER`    | `lib/tdee.ts: BASE_MULTIPLIER` |
| `STEP_KCAL_PER_KG`   | 0.0005 | `TdeeCalculator::STEP_KCAL_PER_KG`   | `lib/tdee.ts: STEP_KCAL_PER_KG` |

**Почему 1.2.** Это sedentary-baseline: сон + базовая жизнедеятельность без
намеренной ходьбы и тренировок. Шаги/тренировки добавляются отдельно — на
этом построена вся идея «не двойного счёта».

**Почему 0.0005.** Эмпирический коэффициент: при средней скорости ходьбы
средний человек тратит ≈ 0.5 ккал на 1000 шагов на 1 кг массы тела.

**С чем сравнивается.** TDEE сравнивается с **съеденными ккал** только в
карточке «Реальный баланс» на /day — для информации. Бейдж дня его НЕ
использует (см. §4).

### 3.2 Activity multiplier (помощник при создании цели)

`activity_level` **не хранится** в профиле, не идёт в TDEE расчёт. Это
локальный helper в калькуляторе цели, чтобы предложить стартовое число kcal:

```
average_TDEE_estimate = round(BMR × ACTIVITY_MULTIPLIER[level])
```

| Уровень | Множитель | Подпись |
|---|---:|---|
| `sedentary` | 1.2   | Сидячая (без спорта) |
| `light`     | 1.4   | Лёгкая (1-2 трен/нед) |
| `moderate`  | 1.55  | Средняя (3-4 трен/нед) |
| `active`    | 1.725 | Высокая (5+ трен/нед) |

Источник: `lib/tdee.ts: ACTIVITY_MULTIPLIER` / `ACTIVITY_LABEL`.

---

## 4. Mode (классификация дня)

```
delta = eaten − goal
pct   = |delta| / goal
```

| Условие | Код | Лейбл |
|---|---|---|
| `pct ≤ ON_TARGET_PCT` | `on_target` | На цели |
| `delta > 0, pct ≤ MODERATE_PCT` | `over` | Перебор |
| `delta > 0, pct > MODERATE_PCT` | `far_over` | Сильный перебор |
| `delta < 0, pct ≤ MODERATE_PCT` | `under` | Недобор |
| `delta < 0, pct > MODERATE_PCT` | `far_under` | Сильный недобор |

| Константа | Значение | Бэк | Фронт |
|---|---:|---|---|
| `ON_TARGET_PCT` | 0.05 | `ModeClassifier::ON_TARGET_PCT` | `lib/modes.ts: ON_TARGET_PCT` |
| `MODERATE_PCT`  | 0.15 | `ModeClassifier::MODERATE_PCT`  | `lib/modes.ts: MODERATE_PCT` |

**С чем сравнивается.** `eaten` это `meals.sum(kcal)`. `goal` это
`goals.kcal` (число, которое юзер сам ввёл/принял при создании цели).
TDEE сюда **не входит**.

**Цвета календаря** `frontend/src/lib/modes.ts: modeColor`:
- `on_target` → зелёный (`--color-accent`)
- `over` / `under` → жёлтый
- `far_over` / `far_under` → красный
- нет данных → нейтральный фон

---

## 5. GoalType (тип цели)

Хранится в `goals.type`. Не вычисляется. Влияет на:
1. Текст чипа на /day («Сушка · день N/M»).
2. Долгосрочный assessment в `WeightTrendInsight`.
3. Стартовый kcal-предложитель.

### 5.1 Стартовое предложение по типу цели

```
suggested_kcal = average_TDEE_estimate + GOAL_TYPE_DELTA[type]
```

| Тип | Дельта от TDEE | Источник |
|---|---:|---|
| `cut`         | −400 | `lib/modes.ts: GOAL_TYPE_DELTA.cut` |
| `maintenance` |    0 | `lib/modes.ts: GOAL_TYPE_DELTA.maintenance` |
| `bulk`        | +300 | `lib/modes.ts: GOAL_TYPE_DELTA.bulk` |

**Почему именно эти числа.**
- −400 ккал = ≈ 0.4 кг/нед потери (1 кг ≈ 7700 ккал, не пересчитываем дома, держим эмпирически).
- +300 ккал — lean-bulk минимум, при котором рост массы преобладает над набором жира.

---

## 6. Macro split

```
protein_g = round(weight × PROTEIN_G_PER_KG)
fat_kcal  = target_kcal × FAT_RATIO
fat_g     = round(fat_kcal / KCAL_PER_FAT_G)
carbs_g   = round((target_kcal − fat_kcal − protein_g × KCAL_PER_PROTEIN_G) / KCAL_PER_CARB_G)
```

| Константа | Значение | Бэк | Фронт |
|---|---:|---|---|
| `PROTEIN_G_PER_KG`  | 1.8  | `Macros::PROTEIN_G_PER_KG` | `lib/macros.ts: DEFAULT_PROTEIN_G_PER_KG` |
| `FAT_RATIO`         | 0.25 | `Macros::FAT_RATIO`        | `lib/macros.ts: DEFAULT_FAT_RATIO` |

**Откуда числа.**
- 1.8 г/кг — середина диапазона 1.6–2.2 г/кг, рекомендованного для людей
  на дефиците или в наборе. Ниже 1.6 — теряется мышечная масса.
- 25% kcal на жир — минимум для гормонального здоровья без вытеснения
  белков и углеводов.

---

## 7. Insights — пороги по правилам

Каждое правило держит свои константы рядом с собой
(`backend/app/Services/Insights/Rules/<Rule>.php`).

### 7.1 EmptyDayInsight
| Имя | Значение | Зачем |
|---|---:|---|
| `PRIORITY` | 90 | Самая высокая среди ежедневных, чтобы пустой день был сразу заметен |

### 7.2 RecoveryAfterOverateInsight
| Имя | Значение | Зачем |
|---|---:|---|
| `PRIORITY` | 85 | Утреннее напоминание выше форкаста |
| `MORNING_CUTOFF_HOUR` | 12 | После полудня сообщение бесполезно |
| `EXCESS_THRESHOLD_KCAL` | 300 | Меньше 300 — это шум, не «вчера переел» |

### 7.3 EndOfDayDeficitInsight
| Имя | Значение | Сравнивается с | Решение |
|---|---:|---|---|
| `END_OF_DAY_HOUR` | 22 | `hoursIntoDay` | gate входа |
| `ON_TRACK_KCAL_BAND` | 200 | `\|eaten − goal\|` | ≤ → good; > → warm/neutral |

`monthly = diff × DAYS_PER_MONTH` для словесной оценки.

### 7.4 ForecastInsight
| Имя | Значение | Что делает |
|---|---:|---|
| `WINDOW_START_HOUR..END` | 14..22 | Окно показа |
| `MIN_MEALS` | 2 | Минимум приёмов чтобы строить прогноз |
| `AVG_MEAL_LOOKBACK_DAYS` | 30 | За сколько дней брать средний приём |
| `TYPICAL_MEALS_PER_DAY` | 4 | Сколько приёмов «обычно бывает за день» |
| `ON_PLAN_BAND` | 100 | ±100 — попал |
| `FAR_UNDER_BAND` | 200 | ниже −200 — большой недобор |

Прогноз: `forecast = totals + max(0, 4 − meals.count) × avg_meal_kcal`.

### 7.5 KcalRemainingInsight
| Имя | Значение |
|---|---:|
| `PRIORITY` | 60 |
| `WINDOW_START_HOUR..END` | 10..22 |

### 7.6 OnlyBreakfastInsight
| Имя | Значение |
|---|---:|
| `PRIORITY` | 50 |
| `REMINDER_AFTER_HOUR` | 13 |

### 7.7 WeightTrendInsight
| Имя | Значение | Что делает |
|---|---:|---|
| `PRIORITY` | 30 | Долгосрочное, ниже остальных |
| `MIN_GOAL_AGE_DAYS` | 14 | Цель должна жить ≥ 2 недель |
| `LOOKBACK_DAYS` | 30 | За сколько дней тащить замеры |
| `MIN_MEASUREMENTS` | 5 | Минимум точек для регрессии |
| `REGRESSION_WINDOW` | 14 | По скольким последним точкам считать |
| `CUT_TOO_FAST_KG_PER_WEEK`  | −1.0  | Быстрее → «слишком быстро» |
| `CUT_SAFE_KG_PER_WEEK`      | −0.5  | На границе «безопасно» |
| `CUT_STALLED_KG_PER_WEEK`   | −0.2  | Медленнее → «замедлилось» |
| `BULK_TOO_FAST_KG_PER_WEEK` | 0.7   | Быстрее → «много жира» |
| `BULK_LEAN_MIN/MAX`         | 0.25 / 0.5 | Окно lean bulk |

`kgPerWeek = slope × DAYS_PER_WEEK`.

---

## 8. Stats (карточка прогресса)

`backend/app/Services/Stats/StatsAggregator.php`.

| Имя | Значение | Что делает |
|---|---:|---|
| `ROLLING_WINDOW_DAYS`  | 7 | Сглаживание линий веса/% жира/обхватов |
| `TREND_MIN_MEASUREMENTS` | 5 | Замеров для подсчёта тренда |
| `TREND_MIN_SPAN_DAYS`    | 7 | Размах в днях для тренда |

Если данных меньше — `trend_kg_per_week = null`, UI скрывает карточку.

---

## 9. DayAggregator

`backend/app/Services/Days/DayAggregator.php`.

| Имя | Значение | Зачем |
|---|---:|---|
| `FALLBACK_WEIGHT_KG` | 80.0 | Когда нет ни одного замера — TDEE считаем на «среднем теле», UI это подсвечивает «запиши вес» |

---

## 10. Кросс-проверки (что важно держать вместе)

1. **Бэк ↔ фронт TDEE** — одинаковые BMR/Base/Steps на одинаковых входах.
   Меняешь `BASE_MULTIPLIER` в одном — обязан в другом.
2. **Бэк ↔ фронт Mode** — одинаковая классификация. Тесты сверяют граничные
   точки `goal × (1 ± 0.05)` и `goal × (1 ± 0.15)`.
3. **Бэк ↔ фронт Macros** — одинаковый split на одинаковых входах.

Параллельные тесты:
- `backend/tests/Unit/TdeeCalculatorTest.php` ↔ `frontend/src/lib/__tests__/tdee.spec.ts`
- `backend/tests/Unit/ModeClassifierTest.php` ↔ `frontend/src/lib/__tests__/modes.spec.ts`

---

## Как добавлять новое число

1. Сформулируй: что это значит, с чем сравнивается, почему именно это значение.
2. Дай ему имя и положи константой в подходящий файл (см. список вверху).
3. Если оно нужно и на бэке, и на фронте — продублируй в обе стороны и добавь тесты.
4. Обнови этот файл — таблицу, в которую константа логически попадает.
5. Если число — порог для UX-решения, добавь его в [docs/06-insights.md](06-insights.md).

Если соблазн воткнуть число прямо в код — остановись. У него должно быть имя.
