<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\User;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ProposeMealTool
{
    public const NAME = 'propose_meal';

    private const SLOTS = ['breakfast', 'lunch', 'snack', 'dinner', 'other'];

    /**
     * Anthropic tool definition.
     *
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        return [
            'name'        => self::NAME,
            'description' =>
                "Предлагает запись приёма пищи в дневник конкретного пользователя. " .
                "Сервер сам считает итоговые ккал/Б/Ж/У по весовой формуле — не считай вручную, просто перечисли " .
                "ингредиенты с КБЖУ на 100г, общий выход блюда и съеденную порцию. " .
                "Если для приёма пищи нужно несколько разных блюд (например \"курица + рис\" одним сообщением) — " .
                "вызови инструмент несколько раз. Для нескольких пользователей — тоже отдельный вызов на каждого. " .
                "ВАЖНО: в КАЖДОМ вызове обязательно передавай target_user_uuid — даже если контекст " .
                "очевиден, даже в десятом подряд вызове, даже если у тебя кончается max_tokens. " .
                "Вызов без target_user_uuid отбрасывается. Если не уверена кому добавить — переспроси текстом " .
                "вместо вызова. " .
                "Не вызывай инструмент, если данных недостаточно (не указан бренд, неизвестное блюдо) — переспроси текстом.",
            'input_schema' => [
                'type'     => 'object',
                'required' => ['target_user_uuid', 'label', 'ingredients'],
                'properties' => [
                    'target_user_uuid' => [
                        'type'        => 'string',
                        'description' => 'UUID пользователя из контекста, кому добавить.',
                    ],
                    'label' => [
                        'type'        => 'string',
                        'description' => 'Короткое название блюда для дневника, например "Куриная грудка" или "Плов".',
                    ],
                    'ingredients' => [
                        'type'        => 'array',
                        'minItems'    => 1,
                        'description' => 'Ингредиенты с КБЖУ на 100г и весом. Для простого блюда — один элемент.',
                        'items' => [
                            'type'     => 'object',
                            'required' => ['name', 'grams', 'kcal_per_100g', 'protein_per_100g', 'fat_per_100g', 'carbs_per_100g'],
                            'properties' => [
                                'name'              => ['type' => 'string'],
                                'grams'             => ['type' => 'number', 'minimum' => 0.1],
                                'kcal_per_100g'     => ['type' => 'number', 'minimum' => 0],
                                'protein_per_100g'  => ['type' => 'number', 'minimum' => 0],
                                'fat_per_100g'      => ['type' => 'number', 'minimum' => 0],
                                'carbs_per_100g'    => ['type' => 'number', 'minimum' => 0],
                            ],
                        ],
                    ],
                    'total_yield_grams' => [
                        'type'        => 'number',
                        'description' => 'Общий вес готового блюда после готовки. Опционально; по умолчанию = сумма весов ингредиентов (для простого блюда без готовки).',
                        'minimum'     => 0.1,
                    ],
                    'eaten_grams' => [
                        'type'        => 'number',
                        'description' => 'Сколько грамм пользователь реально съел. Опционально; по умолчанию = total_yield_grams (всё блюдо).',
                        'minimum'     => 0.1,
                    ],
                    'slot' => [
                        'type' => 'string',
                        'enum' => self::SLOTS,
                        'description' => 'Тип приёма. Опционально, по умолчанию определяется по времени.',
                    ],
                    'eaten_at' => [
                        'type'        => 'string',
                        'description' => 'ISO 8601 datetime приёма пищи. Передавай ТОЛЬКО если пользователь явно указал конкретную дату ("13 мая", "12.05.2026" и т.д.). Если даты нет или сказано "сегодня" — НЕ передавай поле, сервер подставит сейчас. Слова "вчера"/"позавчера"/"завтра" — НЕ передавай поле и сначала переспроси пользователя какая конкретно дата.',
                    ],
                    'notes' => [
                        'type'        => 'string',
                        'description' => 'Свободные заметки от пользователя.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input  validated tool_use.input from Anthropic
     * @return array<string, mixed>  proposal payload to send back as tool_result and store for approval
     */
    public function execute(array $input): array
    {
        // Без явной валидации пустая строка дойдёт до Postgres и упадёт как
        // SQLSTATE 22P02 (invalid input for uuid). Эта ошибка прилетит к
        // LLM как tool_result is_error, и модель теряется. Лучше дать
        // понятное сообщение, чтобы AI смогла переспросить.
        $rawUuid = trim((string) ($input['target_user_uuid'] ?? ''));
        if ($rawUuid === '') {
            throw new InvalidArgumentException('target_user_uuid is required (specify which user gets the meal)');
        }
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $rawUuid)) {
            throw new InvalidArgumentException("target_user_uuid is not a valid UUID: {$rawUuid}");
        }
        $targetUser = User::where('uuid', $rawUuid)->first();
        if (!$targetUser) {
            throw new InvalidArgumentException("Unknown target_user_uuid: {$rawUuid}");
        }

        $label = trim((string) ($input['label'] ?? ''));
        if ($label === '') {
            throw new InvalidArgumentException('label is required');
        }

        $ingredients = $input['ingredients'] ?? [];
        if (!is_array($ingredients) || count($ingredients) === 0) {
            throw new InvalidArgumentException('At least one ingredient is required');
        }

        $sumGrams = 0.0;
        $totalKcal = 0.0;
        $totalProtein = 0.0;
        $totalFat = 0.0;
        $totalCarbs = 0.0;
        $breakdown = [];

        foreach ($ingredients as $i => $ing) {
            if (!is_array($ing)) {
                throw new InvalidArgumentException("ingredient #{$i} is not an object");
            }
            $name  = trim((string) ($ing['name'] ?? ''));
            $grams = (float) ($ing['grams'] ?? 0);
            $kcal100 = (float) ($ing['kcal_per_100g'] ?? 0);
            $p100    = (float) ($ing['protein_per_100g'] ?? 0);
            $f100    = (float) ($ing['fat_per_100g'] ?? 0);
            $c100    = (float) ($ing['carbs_per_100g'] ?? 0);

            if ($name === '' || $grams <= 0) {
                throw new InvalidArgumentException("ingredient #{$i} requires non-empty name and positive grams");
            }

            $kcal    = $grams * $kcal100 / 100.0;
            $protein = $grams * $p100 / 100.0;
            $fat     = $grams * $f100 / 100.0;
            $carbs   = $grams * $c100 / 100.0;

            $sumGrams     += $grams;
            $totalKcal    += $kcal;
            $totalProtein += $protein;
            $totalFat     += $fat;
            $totalCarbs   += $carbs;

            $breakdown[] = [
                'name'     => $name,
                'grams'    => round($grams, 1),
                'kcal'     => round($kcal, 1),
                'protein_g' => round($protein, 2),
                'fat_g'    => round($fat, 2),
                'carbs_g'  => round($carbs, 2),
            ];
        }

        $totalYield = isset($input['total_yield_grams']) ? (float) $input['total_yield_grams'] : $sumGrams;
        $eaten      = isset($input['eaten_grams']) ? (float) $input['eaten_grams'] : $totalYield;

        if ($totalYield <= 0) {
            throw new InvalidArgumentException('total_yield_grams must be positive');
        }
        if ($eaten <= 0) {
            throw new InvalidArgumentException('eaten_grams must be positive');
        }

        $factor = $eaten / $totalYield;

        $finalKcal    = round($totalKcal * $factor, 1);
        $finalProtein = round($totalProtein * $factor, 2);
        $finalFat     = round($totalFat * $factor, 2);
        $finalCarbs   = round($totalCarbs * $factor, 2);

        // Поле eaten_at AI должна передавать только когда пользователь явно
        // назвал конкретную дату ("13 мая X") — тогда уважаем. Если AI
        // нарушит правило и подставит eaten_at для слова "вчера" — что ж,
        // карточка покажет реальную дату и пользователь увидит/откажет.
        // Если поля нет — берём сейчас (правильно для "сегодня"/"только что").
        $tz = $targetUser->timezone ?? 'UTC';
        $eatenAt = isset($input['eaten_at']) && trim((string) $input['eaten_at']) !== ''
            ? CarbonImmutable::parse((string) $input['eaten_at'])->setTimezone($tz)
            : CarbonImmutable::now($tz);

        $slot = isset($input['slot']) && in_array($input['slot'], self::SLOTS, true)
            ? (string) $input['slot']
            : self::slotFromTime($eatenAt);

        return [
            'target_user' => [
                'uuid' => $targetUser->uuid,
                'name' => $targetUser->name,
            ],
            'label'              => $label,
            'eaten_grams'        => round($eaten, 1),
            'total_yield_grams'  => round($totalYield, 1),
            'kcal'               => $finalKcal,
            'protein_g'          => $finalProtein,
            'fat_g'              => $finalFat,
            'carbs_g'            => $finalCarbs,
            'slot'               => $slot,
            'eaten_at'           => $eatenAt->toIso8601String(),
            'ingredients_breakdown' => $breakdown,
            'notes'              => isset($input['notes']) ? trim((string) $input['notes']) : null,
        ];
    }

    private static function slotFromTime(CarbonImmutable $t): string
    {
        $h = (int) $t->format('H');
        // 0-3 — поздняя ночь, относится к предыдущему дню (см. LogicalDate);
        // помещаем в 'other' чтобы ночные приёмы не оседали в "завтрак".
        return match (true) {
            $h < 3  => 'other',
            $h < 11 => 'breakfast',
            $h < 15 => 'lunch',
            $h < 18 => 'snack',
            $h < 22 => 'dinner',
            default => 'other',
        };
    }
}
