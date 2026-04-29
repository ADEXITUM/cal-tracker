# 02. Database

PostgreSQL 16. Конвенции:

- PK: `BIGSERIAL`. Public ref: `uuid UUID NOT NULL UNIQUE DEFAULT gen_random_uuid()` — везде
- Все timestamps `TIMESTAMPTZ`, даты дней `DATE`
- `created_at`, `updated_at` (Laravel-managed)
- FK с `ON DELETE CASCADE` для owned-связей
- Индексы на все FK + по полям фильтрации

## Таблицы

### users
Стандартная Laravel + email unique, name, `avatar_color VARCHAR(7) DEFAULT '#FF5A1F'`, `timezone VARCHAR(64) DEFAULT 'Europe/Moscow'`, password (bcrypt). Открытая регистрация, без ролей и админки.

### personal_access_tokens
Создаётся `php artisan install:api`, не пишем вручную.

### profiles (1:1 к users)
- `gender` `male`/`female` (для Mifflin-St Jeor)
- `birth_date DATE`
- `height_cm SMALLINT 100..250`
- `activity_level` `sedentary`/`light`/`moderate`/`active`

### goals
Цели КБЖУ периодами. На один день действует один goal.

- `start_date DATE NOT NULL`
- `end_date DATE NULL` (NULL = до следующего goal)
- `kcal SMALLINT 800..6000`, `protein_g 0..500`, `fat_g 0..400`, `carbs_g 0..1000`
- `note VARCHAR(120) NULL`

Резолв для дня: `WHERE start_date <= ? AND (end_date IS NULL OR end_date >= ?) ORDER BY start_date DESC LIMIT 1`. При создании нового открытого goal (end_date NULL) — закрыть предыдущий открытый автоматически (`end_date = new.start_date - 1 day`).

Индекс: `(user_id, start_date DESC)`.

### day_entries
Агрегат за день, lazy-create. `UNIQUE(user_id, date)`.

- `mood SMALLINT 1..5 NULL`, `wellbeing 1..5 NULL`, `sleep_hours NUMERIC(3,1) NULL`
- `steps INTEGER NULL 0..200000`
- `notes TEXT NULL`

### measurements
Замеры веса и состава. Несколько за день можно. Все поля биоимпеданса nullable.

- FK: `day_entry_id`, денорм `user_id` (для скорости)
- `measured_at TIMESTAMPTZ`
- `weight_kg NUMERIC(5,2) 30..300` (обязательное)
- nullable: `body_fat_pct`, `muscle_mass_kg`, `body_water_pct`, `visceral_fat_level SMALLINT 1..30`, `bone_mass_kg`, `protein_pct`, `heart_rate_bpm`
- `source VARCHAR(20) DEFAULT 'manual'`

### dishes
Личная база блюд юзера. КБЖУ на 100 г.

- `name VARCHAR(120)`
- `kcal_per_100g`, `protein_per_100g`, `fat_per_100g`, `carbs_per_100g` — все NUMERIC
- `usage_count INTEGER DEFAULT 0` (auto-increment через model event при создании meal)
- `last_used_at TIMESTAMPTZ NULL`
- `archived_at TIMESTAMPTZ NULL` (soft delete)

### meals
Приёмы пищи. Snapshot КБЖУ — даже если юзер позже изменит dish, исторические meals не пересчитываются.

- FK: `day_entry_id`, денорм `user_id`
- `slot` `breakfast`/`lunch`/`snack`/`dinner`/`other`
- `eaten_at TIMESTAMPTZ`
- Либо `dish_id + grams`, либо `name + ad-hoc kcal/БЖУ`. CHECK constraint на это.
- **Всегда** заполнены: `kcal`, `protein_g`, `fat_g`, `carbs_g` (snapshot)
- При delete dish → `dish_id SET NULL`, meal остаётся

### workouts
- FK: `day_entry_id`, денорм `user_id`
- `name VARCHAR(120)` (свободный текст)
- `duration_min SMALLINT NULL`, `kcal_burned SMALLINT NULL`
- `notes VARCHAR(500) NULL`

## Денормализация — инварианты

1. `measurements.user_id`, `meals.user_id`, `workouts.user_id` — поддерживается через Eloquent `creating` event
2. `meals.kcal/protein/fat/carbs` — snapshot, не пересчитывается
3. `dishes.usage_count`, `last_used_at` — auto-increment через event при создании meal с этим dish

## Порядок миграций

users → personal_access_tokens → profiles → goals → day_entries → dishes → measurements → meals → workouts

## Seeder (только local/testing)

Dev user (`dev@example.com` / `password`), profile (male 1992, 180см, sedentary), один активный goal (1700 / 150 / 60 / 140), 30 дней истории с реалистичными meals и весами, 10 базовых dishes (овсянка, творог 5%, куриная грудка варёная, гречка, яйцо, банан, молоко 2.5%, оливковое масло, авокадо, греческий йогурт).

## Open questions

- Soft delete для day_entries/meals — **не делаем**, удалил так удалил
- Materialized views для агрегатов — пока не нужны, считаем на лету
