<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Services\Chat\Tools\ProposeMealTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProposeMealToolTest extends TestCase
{
    use RefreshDatabase;

    private function tool(): ProposeMealTool
    {
        return new ProposeMealTool();
    }

    public function test_simple_meal_computes_direct_kbju(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);

        $result = $this->tool()->execute([
            'target_user_uuid' => $user->uuid,
            'label'            => 'Куриная грудка',
            'ingredients'      => [[
                'name'             => 'грудка',
                'grams'            => 200,
                'kcal_per_100g'    => 165,
                'protein_per_100g' => 31,
                'fat_per_100g'     => 3.6,
                'carbs_per_100g'   => 0,
            ]],
        ]);

        $this->assertSame($user->uuid, $result['target_user']['uuid']);
        $this->assertSame('Куриная грудка', $result['label']);
        $this->assertSame(330.0, $result['kcal']);
        $this->assertSame(62.0, $result['protein_g']);
        $this->assertEquals(7.2, $result['fat_g']);
        $this->assertSame(0.0, $result['carbs_g']);
        $this->assertSame(200.0, $result['eaten_grams']);
        $this->assertSame(200.0, $result['total_yield_grams']);
    }

    public function test_composite_dish_applies_eaten_factor(): void
    {
        $user = User::factory()->create();

        // Plov: 100g rice raw (365 kcal/100g) + 100g lamb (280 kcal/100g)
        // Total raw kcal = 365 + 280 = 645
        // total_yield = 500g cooked. Eaten 100g.
        // factor = 100/500 = 0.2 → final kcal = 645 * 0.2 = 129
        $result = $this->tool()->execute([
            'target_user_uuid'  => $user->uuid,
            'label'             => 'Плов (порция)',
            'ingredients' => [
                ['name' => 'рис', 'grams' => 100, 'kcal_per_100g' => 365, 'protein_per_100g' => 7, 'fat_per_100g' => 1, 'carbs_per_100g' => 78],
                ['name' => 'баранина', 'grams' => 100, 'kcal_per_100g' => 280, 'protein_per_100g' => 17, 'fat_per_100g' => 23, 'carbs_per_100g' => 0],
            ],
            'total_yield_grams' => 500,
            'eaten_grams'       => 100,
        ]);

        $this->assertEquals(129.0, $result['kcal']);
        $this->assertEquals(4.8, $result['protein_g']);  // (7+17) * 0.2 = 4.8
        $this->assertEquals(4.8, $result['fat_g']);      // (1+23) * 0.2 = 4.8
        $this->assertEquals(15.6, $result['carbs_g']);   // (78+0) * 0.2 = 15.6
        $this->assertSame(100.0, $result['eaten_grams']);
        $this->assertSame(500.0, $result['total_yield_grams']);
    }

    public function test_slot_defaults_to_time_of_day(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $simple = [
            'target_user_uuid' => $user->uuid,
            'label'            => 'X',
            'ingredients'      => [['name' => 'X', 'grams' => 100, 'kcal_per_100g' => 100, 'protein_per_100g' => 1, 'fat_per_100g' => 1, 'carbs_per_100g' => 1]],
        ];

        $this->travelTo('2026-05-13 08:00:00');
        $this->assertSame('breakfast', $this->tool()->execute($simple)['slot']);

        $this->travelTo('2026-05-13 13:00:00');
        $this->assertSame('lunch', $this->tool()->execute($simple)['slot']);

        $this->travelTo('2026-05-13 20:00:00');
        $this->assertSame('dinner', $this->tool()->execute($simple)['slot']);

        $this->travelTo('2026-05-14 02:00:00');
        $this->assertSame('other', $this->tool()->execute($simple)['slot']);

        $this->travelBack();
    }

    public function test_explicit_slot_overrides_time(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $this->travelTo('2026-05-13 08:00:00');

        $result = $this->tool()->execute([
            'target_user_uuid' => $user->uuid,
            'label'            => 'X',
            'ingredients'      => [['name' => 'X', 'grams' => 100, 'kcal_per_100g' => 100, 'protein_per_100g' => 1, 'fat_per_100g' => 1, 'carbs_per_100g' => 1]],
            'slot'             => 'dinner',
        ]);
        $this->assertSame('dinner', $result['slot']);

        $this->travelBack();
    }

    public function test_eaten_at_is_respected_when_explicitly_provided(): void
    {
        // AI должна передавать eaten_at только когда юзер явно назвал дату.
        // Сервер уважает это значение (для записи прошлых/будущих дней
        // через "13 мая X").
        $user = User::factory()->create(['timezone' => 'UTC']);
        $this->travelTo('2026-05-14 12:00:00');

        $result = $this->tool()->execute([
            'target_user_uuid' => $user->uuid,
            'label'            => 'X',
            'ingredients'      => [['name' => 'X', 'grams' => 100, 'kcal_per_100g' => 100, 'protein_per_100g' => 1, 'fat_per_100g' => 1, 'carbs_per_100g' => 1]],
            'eaten_at'         => '2026-05-13T13:00:00+00:00',
        ]);
        $this->assertStringStartsWith('2026-05-13T13:00:00', $result['eaten_at']);

        $this->travelBack();
    }

    public function test_eaten_at_defaults_to_now_when_omitted(): void
    {
        // Если AI не передаёт eaten_at — берём сейчас. Это работает в паре
        // с серверной LogicalDate: meal попадёт в текущий логический день.
        $user = User::factory()->create(['timezone' => 'UTC']);
        $this->travelTo('2026-05-14 12:00:00');

        $result = $this->tool()->execute([
            'target_user_uuid' => $user->uuid,
            'label'            => 'X',
            'ingredients'      => [['name' => 'X', 'grams' => 100, 'kcal_per_100g' => 100, 'protein_per_100g' => 1, 'fat_per_100g' => 1, 'carbs_per_100g' => 1]],
        ]);
        $this->assertStringStartsWith('2026-05-14T12:00:00', $result['eaten_at']);

        $this->travelBack();
    }

    public function test_rejects_unknown_target_user(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->tool()->execute([
            'target_user_uuid' => '00000000-0000-0000-0000-000000000000',
            'label'            => 'X',
            'ingredients'      => [['name' => 'X', 'grams' => 100, 'kcal_per_100g' => 100, 'protein_per_100g' => 0, 'fat_per_100g' => 0, 'carbs_per_100g' => 0]],
        ]);
    }

    public function test_rejects_empty_ingredients(): void
    {
        $user = User::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        $this->tool()->execute([
            'target_user_uuid' => $user->uuid,
            'label'            => 'X',
            'ingredients'      => [],
        ]);
    }

    public function test_rejects_zero_grams(): void
    {
        $user = User::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        $this->tool()->execute([
            'target_user_uuid' => $user->uuid,
            'label'            => 'X',
            'ingredients'      => [['name' => 'X', 'grams' => 0, 'kcal_per_100g' => 100, 'protein_per_100g' => 0, 'fat_per_100g' => 0, 'carbs_per_100g' => 0]],
        ]);
    }
}
