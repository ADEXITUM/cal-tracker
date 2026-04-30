<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Dish;
use App\Models\Goal;
use App\Models\Meal;
use App\Models\Measurement;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DayTest extends TestCase
{
    use RefreshDatabase;

    private function userWithProfile(): User
    {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id, 'gender' => 'male', 'birth_date' => '1992-01-01', 'height_cm' => 180]);
        Goal::factory()->create(['user_id' => $user->id, 'start_date' => '2020-01-01', 'type' => 'maintenance', 'kcal' => 1700, 'protein_g' => 150, 'fat_g' => 60, 'carbs_g' => 140]);
        return $user->load('profile');
    }

    public function test_get_day_returns_empty_structure_when_no_entry(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->getJson('/api/v1/days/2026-01-15')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-01-15')
            ->assertJsonPath('data.day_entry', null)
            ->assertJsonPath('data.totals.kcal', 0);
    }

    public function test_get_day_returns_goal_and_mode(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->getJson('/api/v1/days/2026-01-15')
            ->assertOk()
            ->assertJsonPath('data.goal.kcal', 1700)
            ->assertJsonStructure(['data' => ['mode' => ['code', 'label', 'delta_kcal']]]);
    }

    public function test_put_day_creates_entry(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->putJson('/api/v1/days/2026-01-15', ['steps' => 8000, 'mood' => 4])
            ->assertOk();

        $this->assertDatabaseHas('day_entries', ['user_id' => $user->id, 'steps' => 8000]);
    }

    public function test_add_meal_adhoc(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->postJson('/api/v1/days/2026-01-15/meals', [
                'slot'      => 'breakfast',
                'eaten_at'  => '2026-01-15T08:00:00Z',
                'name'      => 'Овсянка',
                'kcal'      => 350,
                'protein_g' => 12,
                'fat_g'     => 6,
                'carbs_g'   => 55,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Овсянка')
            ->assertJsonPath('data.kcal', 350);
    }

    public function test_add_meal_from_dish_computes_snapshot(): void
    {
        $user = $this->userWithProfile();
        $dish = Dish::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson('/api/v1/days/2026-01-15/meals', [
                'slot'      => 'lunch',
                'eaten_at'  => '2026-01-15T12:00:00Z',
                'dish_uuid' => $dish->uuid,
                'grams'     => 200,
            ])
            ->assertStatus(201);

        $meal = Meal::first();
        $expected = round($dish->kcal_per_100g * 2, 2);
        $this->assertEquals($expected, $meal->kcal);
    }

    public function test_dish_snapshot_not_affected_by_dish_update(): void
    {
        $user = $this->userWithProfile();
        $dish = Dish::factory()->create(['user_id' => $user->id, 'kcal_per_100g' => 100]);

        $this->actingAs($user)->postJson('/api/v1/days/2026-01-15/meals', [
            'slot' => 'lunch', 'eaten_at' => '2026-01-15T12:00:00Z',
            'dish_uuid' => $dish->uuid, 'grams' => 100,
        ]);

        $dish->update(['kcal_per_100g' => 999]);

        $meal = Meal::first();
        $this->assertEquals(100.0, $meal->kcal);
    }

    public function test_add_measurement(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->postJson('/api/v1/days/2026-01-15/measurements', [
                'measured_at' => '2026-01-15T07:00:00Z',
                'weight_kg'   => 82.5,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.weight_kg', 82.5);
    }

    public function test_delete_meal(): void
    {
        $user = $this->userWithProfile();
        $this->actingAs($user)->postJson('/api/v1/days/2026-01-15/meals', [
            'slot' => 'lunch', 'eaten_at' => '2026-01-15T12:00:00Z',
            'name' => 'Test', 'kcal' => 100, 'protein_g' => 10, 'fat_g' => 5, 'carbs_g' => 10,
        ]);

        $meal = Meal::first();
        $this->actingAs($user)->deleteJson("/api/v1/meals/{$meal->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('meals', ['id' => $meal->id]);
    }

    public function test_cannot_access_other_users_day(): void
    {
        $user1 = $this->userWithProfile();
        $user2 = $this->userWithProfile();

        $this->actingAs($user2)->postJson('/api/v1/days/2026-01-15/meals', [
            'slot' => 'lunch', 'eaten_at' => '2026-01-15T12:00:00Z',
            'name' => 'Private', 'kcal' => 100, 'protein_g' => 5, 'fat_g' => 3, 'carbs_g' => 10,
        ]);
        $meal = Meal::first();

        $this->actingAs($user1)->deleteJson("/api/v1/meals/{$meal->uuid}")->assertNotFound();
    }
}
