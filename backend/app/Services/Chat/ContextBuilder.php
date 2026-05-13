<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\DayEntry;
use App\Models\Dish;
use App\Models\User;
use App\Services\Goals\GoalResolver;
use App\Support\LogicalDate;
use Carbon\Carbon;

class ContextBuilder
{
    /**
     * Build the system prompt for a chat turn.
     *
     * Returns an array of content blocks (Anthropic shape). The static rules
     * block is marked cache_control: ephemeral so it can be reused across
     * turns without paying the full input price each time.
     *
     * @return list<array<string, mixed>>
     */
    public function build(User $currentUser): array
    {
        return [
            [
                'type'          => 'text',
                'text'          => $this->staticRules(),
                'cache_control' => ['type' => 'ephemeral'],
            ],
            [
                'type' => 'text',
                'text' => $this->dynamicContext($currentUser),
            ],
        ];
    }

    private function staticRules(): string
    {
        return <<<PROMPT
Ты — помощник, который записывает приёмы пищи в дневник КБЖУ для членов одного домохозяйства.
Твоя задача — распарсить сообщение пользователя в структуру и вызвать инструмент propose_meal.

ИНСТРУМЕНТ propose_meal:
- Один вызов = один приём пищи одному пользователю.
- Если в одном сообщении несколько блюд или несколько пользователей — вызывай инструмент несколько раз подряд.
- Перечисляй ингредиенты с КБЖУ на 100г и весом. Не считай итоговые ккал/Б/Ж/У сам — сервер посчитает по весовой формуле.
- Для составных блюд (готовка с водой/маслом): total_yield_grams = вес готового блюда; eaten_grams = реально съеденная порция.
- Для простого блюда без готовки (например "200г грудки") — total_yield_grams и eaten_grams можно не указывать, сервер возьмёт сумму весов ингредиентов.
- Слот (завтрак/обед/...) определится по времени отправки автоматически, передавай явно только если пользователь его назвал.

КАК ОБРАЩАТЬСЯ К ПОЛЬЗОВАТЕЛЯМ:
- Чат общий между несколькими админами, в каждом сообщении ниже указано "от:".
- "мне", "себе", "я" — тот, кто прислал последнее сообщение (см. "от:" в этом сообщении).
- Имя или ник пользователя из списка — обращайся по имени/uuid.
- "обоим", "нам" — двум сразу (отдельный вызов на каждого).

КАК ОБРАЩАТЬСЯ С БРЕНДОВЫМИ ПРОДУКТАМИ:
- По умолчанию **используй свои знания из training data** — ты помнишь масс-маркет
  бренды (Савушкин, Простоквашино, Эпика, Данон, Активиа, Чудо, Сникерс, Bombbar,
  Папа Может, ВкусВилл и подобные). Вызывай propose_meal с теми значениями, которые
  помнишь, без переспросов. Если конкретный вкус/жирность не уверена — выбери
  типовое значение и зафиксируй это в notes.
- Если действительно не помнишь продукт или вариацию — скажи прямо: "точно не помню
  КБЖУ этой марки, скажи цифры с упаковки". **НИКОГДА** не отвечай "у меня нет
  доступа в интернет" — это вводит пользователя в заблуждение, ты используешь
  обучающую выборку, а не поиск.
- Если пользователь дал КБЖУ инлайн в сообщении ("йогурт 130/10/4/16 на 100г") —
  используй его цифры буквально, твои знания не нужны.

КОГДА ВСЁ-ТАКИ ПЕРЕСПРАШИВАТЬ:
- Не понятен вес блюда (например "пельмени" без количества).
- Не ясно кому добавлять (нет имени и в чате несколько отправителей).
- Действительно неизвестный/редкий бренд который ты не помнишь.

СТИЛЬ:
- Отвечай по-русски, кратко, по делу.
- Не повторяй цифры, которые уже видны в карточке предложения — пользователь их увидит.
- Если предложил блюдо — заверши коротким текстом, например "Готово, проверь и подтверди".
PROMPT;
    }

    private function dynamicContext(User $currentUser): string
    {
        $tz = $currentUser->timezone ?? 'UTC';
        $now = Carbon::now($tz);
        $logicalToday = LogicalDate::today($tz);

        $sections = [];
        $sections[] = "СЕЙЧАС: " . $now->toIso8601String() . " ({$tz})";
        $sections[] = sprintf(
            "ТЕКУЩИЙ ДЕНЬ ДНЕВНИКА: %s (с учётом %d-часового ночного гэпа — еда до %02d:00 утра относится к предыдущему дню).",
            $logicalToday->toDateString(),
            LogicalDate::CUTOFF_HOUR,
            LogicalDate::CUTOFF_HOUR,
        );
        $sections[] = "";
        $sections[] = "ПРАВИЛА ПО ДАТАМ:";
        $sections[] = "1) Пользователь явно назвал дату (\"13 мая\", \"12.05\", \"14.05.2026\") → передай eaten_at в propose_meal с этой датой. Время = указанное пользователем или 12:00 если не указано. Формат ISO 8601 с TZ, пример: \"2026-05-13T12:00:00" . $now->format('P') . "\".";
        $sections[] = "2) Дата НЕ упомянута ИЛИ сказано \"сегодня\" → НЕ передавай eaten_at, сервер подставит сейчас.";
        $sections[] = "3) Сказано слово без конкретной даты (\"вчера\", \"позавчера\", \"завтра\", \"на прошлой неделе\", \"в понедельник\") → НЕ вызывай propose_meal. Переспроси: \"Какая конкретно дата? Напиши в формате '13 мая' или '13.05'.\". После ответа пользователя — действуй по правилу 1.";
        $sections[] = "";
        $sections[] = "ОТПРАВИТЕЛЬ ПОСЛЕДНЕГО СООБЩЕНИЯ: {$currentUser->name} (uuid: {$currentUser->uuid})";
        $sections[] = "";
        $sections[] = "ВСЕ ПОЛЬЗОВАТЕЛИ В СИСТЕМЕ (можно добавлять любому):";

        $users = User::orderBy('id')->get();
        foreach ($users as $i => $u) {
            $userLogicalToday = LogicalDate::today($u->timezone ?? 'UTC');
            $goal = GoalResolver::forDate($u, Carbon::parse($userLogicalToday->toDateString()));
            $entry = DayEntry::with('meals')
                ->where('user_id', $u->id)
                ->whereDate('date', $userLogicalToday->toDateString())
                ->first();

            $eaten = ['kcal' => 0.0, 'p' => 0.0, 'f' => 0.0, 'c' => 0.0];
            if ($entry) {
                foreach ($entry->meals as $m) {
                    $eaten['kcal'] += (float) $m->kcal;
                    $eaten['p']    += (float) $m->protein_g;
                    $eaten['f']    += (float) $m->fat_g;
                    $eaten['c']    += (float) $m->carbs_g;
                }
            }

            $line = sprintf(
                "%d. %s (uuid: %s)",
                $i + 1,
                $u->name,
                $u->uuid,
            );
            if ($goal) {
                $line .= sprintf(
                    "\n   Цель: %d ккал, %dБ/%dЖ/%dУ",
                    $goal->kcal,
                    $goal->protein_g,
                    $goal->fat_g,
                    $goal->carbs_g,
                );
                $line .= sprintf(
                    "\n   Съел сегодня: %.0f ккал, %.0fБ/%.0fЖ/%.0fУ",
                    $eaten['kcal'],
                    $eaten['p'],
                    $eaten['f'],
                    $eaten['c'],
                );
                $remKcal = $goal->kcal - $eaten['kcal'];
                $remP    = $goal->protein_g - $eaten['p'];
                $remF    = $goal->fat_g - $eaten['f'];
                $remC    = $goal->carbs_g - $eaten['c'];
                $line .= sprintf(
                    "\n   Осталось: %.0f ккал, %.0fБ/%.0fЖ/%.0fУ",
                    $remKcal,
                    $remP,
                    $remF,
                    $remC,
                );
            } else {
                $line .= "\n   Цель не задана.";
                $line .= sprintf(
                    "\n   Съел сегодня: %.0f ккал, %.0fБ/%.0fЖ/%.0fУ",
                    $eaten['kcal'],
                    $eaten['p'],
                    $eaten['f'],
                    $eaten['c'],
                );
            }

            // Сохранённые блюда юзера — топ по usage_count. Если пользователь
            // упомянет имя совпадающего блюда, AI берёт КБЖУ отсюда (вместо
            // training-data знания) — это даёт консистентность с тем что
            // юзер сам ввёл в "Блюда".
            $dishes = Dish::where('user_id', $u->id)
                ->whereNull('archived_at')
                ->orderByDesc('usage_count')
                ->orderByDesc('last_used_at')
                ->limit(12)
                ->get();
            if ($dishes->isNotEmpty()) {
                $line .= "\n   Сохранённые блюда (используй их КБЖУ при упоминании):";
                foreach ($dishes as $d) {
                    $line .= sprintf(
                        "\n     - \"%s\": %d/%.1f/%.1f/%.1f на 100г",
                        $d->name,
                        (int) round((float) $d->kcal_per_100g),
                        (float) $d->protein_per_100g,
                        (float) $d->fat_per_100g,
                        (float) $d->carbs_per_100g,
                    );
                }
            }
            $sections[] = $line;
        }

        return implode("\n", $sections);
    }
}
