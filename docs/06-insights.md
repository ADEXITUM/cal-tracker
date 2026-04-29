# 06. Insights и режимы

## Тон

1. Факт + прогноз + (опц.) рычаг. Не «молодец», а «−0.5 кг/нед, к маю −2 кг»
2. Никаких эмоций «хорошо/плохо». Только числа и направление
3. После переедания — recovery, не «компенсируем» (это критично, иначе цикл срыв→голодовка→срыв)
4. Максимум 1-2 подсказки на главном экране. Если кандидатов больше — берём с наивысшим priority
5. Подсказки можно дисмиссить свайпом → не показывать на сегодня (`localStorage[dismissed_insights_${date}]`)

## Тоны (визуальные)

- `neutral` — серый бордер, иконка Info
- `good` — зелёный, Check
- `warm` — accent, Compass
- `warn` — yellow, AlertTriangle (не используем в MVP)
- `alert` — red, AlertOctagon (не используем в MVP)

## TDEE расчёт

Service `Tdee\TdeeCalculator`. Зеркало в `frontend/src/lib/tdee.ts` (для live preview при создании цели). Тесты на бэке и фронте проверяют одинаковые числа на одинаковых входах.

Алгоритм:

1. **BMR (Mifflin-St Jeor)** на последнем известном весе:
   - male: `10*weight + 6.25*height - 5*age + 5`
   - female: `10*weight + 6.25*height - 5*age - 161`
2. **Activity multiplier:** sedentary 1.2, light 1.375, moderate 1.55, active 1.725
   - `baseActivity = bmr * (multiplier - 1)`
3. **Steps bonus** (если ввёл):
   - `stepsKcal = steps * weight * 0.0005`
   - Коэффициент чтобы не считать дважды: sedentary 1.0, light 0.7, moderate 0.4, active 0.2
4. **Workouts bonus:** `workouts.sum(kcal_burned)`

Total = BMR + baseActivity + stepsKcal + workoutsKcal

## Классификация режима

`Modes\ModeClassifier::classify(goalKcal, tdeeKcal)` возвращает Mode по delta = goal − tdee:

- delta < −600 → `extreme_cut` "Экстрим-сушка"
- −600..−300 → `cut` "Сушка"
- −300..−100 → `cut_lite` "Лёгкая сушка"
- −100..+100 → `maintenance` "Поддержка"
- +100..+300 → `light_bulk` "Лёгкий набор"
- > +300 → `bulk` "Набор"

Пороги в `config/modes.php`. Frontend mirror в `lib/modes.ts`.

## Тексты режимов (для explainer modal)

- **extreme_cut:** Очень большой дефицит >25%. Ок на 4 недели максимум. На длинной дистанции даёт срывы и потерю мышц. Рекомендую увеличить ккал.
- **cut:** Средний дефицит. Безопасно 6-8 недель, потом diet break 1-2 недели. Прогресс на весах через 2-3 недели.
- **cut_lite:** Небольшой дефицит. Медленнее но проще держать долго (3+ месяцев). Хорошо для рекомпозиции.
- **maintenance:** Калории около нормы. Стабилизация веса. Идеально для diet break или образа жизни.
- **light_bulk:** Небольшой профицит. Медленный набор массы с минимумом жира.
- **bulk:** Профицит для активного набора. Часть прибавки — жир, это нормально. После 3-4 месяцев — на сушку.

## Mode explainer modal

Открывается тапом на AModeBadge. Содержит:
1. Заголовок режима + иконка
2. Расчёт: TDEE / Цель / Разница (ккал и %)
3. Что это значит (текст из словаря выше)
4. Прогноз для cut/bulk: при темпе X ккал/день — потеря Y кг/нед, на N дней — Z кг
5. Безопасность: до 1% веса/нед безопасно. Текущий темп — в норме / на границе / выше
6. CTA «Изменить цель» → /goals

## InsightEngine

Service. Каждое правило реализует `InsightInterface { evaluate(InsightContext): ?Insight; priority(): int }`. Engine вызывает все, фильтрует non-null, сортирует по priority DESC, возвращает топ-1 или топ-2.

`InsightContext` содержит: `User`, `Carbon $date`, `?DayEntry`, `Goal`, `TdeeBreakdown`, `Mode`, `array $totals`, `Collection $meals/measurements`, `int $hoursIntoDay` (локальное время юзера).

## Insights MVP

### EmptyDayInsight (priority 90)
Условие: `meals.empty && measurements.empty`.
- Today: «Запиши вес или приём чтобы отслеживать день»
- Past: «Не было замеров. Запиши хотя бы вес — тренд важнее идеала»
- Tone: neutral

### OnlyBreakfastInsight (priority 50)
Условие: только slot=breakfast, currentTime > 13:00.
Текст: «Записан только завтрак. Не забудь обед». Tone: neutral.

### KcalRemainingInsight (priority 60)
Условие: `totals.kcal < goal.kcal && hoursIntoDay 10..22`.
Текст: «Осталось {N} ккал. БЖУ: Б {} Ж {} У {}». Tone: neutral.

### ForecastInsight (priority 70)
Условие: `meals.count >= 2 && hoursIntoDay 14..22`.
Расчёт: `avgMealKcal = avg(meals_last_30_days.kcal)`, `remainingMeals = 4 - meals.count`, `forecast = totals + remainingMeals * avgMealKcal`.
- forecast в ±100 от goal: «Идёшь по плану. Прогноз дня: ~{forecast}». Tone: good.
- forecast > goal+100: «По темпу к концу дня ~{forecast} (+{over}). Можно сократить ужин или добавить активность». Tone: warm.
- forecast < goal-200: «По темпу к концу дня ~{forecast} (−{under}). Не урезай ниже цели — это контрпродуктивно». Tone: warm.

### EndOfDayDeficitInsight (priority 80)
Условие: `hoursIntoDay >= 22`.
- В пределах ±200 от цели: «Дефицит дня: {N}. По плану». Tone: good.
- Перебор > 200: «Сегодня +{N} от плана. Это {monthly_impact} к месяцу. Завтра — план тот же, не урезай». Tone: warm.
- Недобор > 300: «Сегодня большой дефицит {N}. Если намеренно — ок. Если случайно — стоит добавить». Tone: neutral.

### RecoveryAfterOverateInsight (priority 85) — критично
Условие: `today == startOfDay && yesterday.totals.kcal > yesterday.goal.kcal + 300`.
Текст: «Вчера было +{excess} от плана. Сегодня план тот же — не урезай больше, это контрпродуктивно. Просто продолжай». Tone: warm.

### WeightTrendInsight (priority 30)
Условие: прошло 14+ дней с старта текущей цели.
Расчёт: линейная регрессия по 14 последним замерам.
Текст: «Темп за 2 недели: {kgPerWeek} кг/нед. {assessment}»:
- cut, темп −0.5..−1 кг/нед: «Безопасная скорость»
- cut, темп быстрее −1: «Быстрее безопасного — стоит добавить ккал»
- cut, темп медленнее −0.2: «Прогресс замедлился — возможно адаптация»
- bulk, темп +0.25..+0.5: «Lean bulk темп»
- bulk, быстрее +0.7: «Слишком быстро — много жира будет»

## Тесты

Каждый insight — отдельный класс с pure `evaluate()`. Юнит-тесты с фабрикой `makeContext([...])` для разных входных данных. Покрываются все ветки tone.

## Open questions

- Хранить dismissed insights на бэке для синка между устройствами — **нет**, это per-day, локально на фронте достаточно
