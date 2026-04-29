<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_goal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/goals', [
                'start_date' => '2026-01-01',
                'end_date'   => null,
                'kcal'       => 1700,
                'protein_g'  => 150,
                'fat_g'      => 60,
                'carbs_g'    => 140,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.kcal', 1700)
            ->assertJsonStructure(['data' => ['uuid', 'start_date', 'kcal']]);
    }

    public function test_creating_open_goal_closes_previous(): void
    {
        $user = User::factory()->create();
        $old = Goal::factory()->create([
            'user_id'    => $user->id,
            'start_date' => '2026-01-01',
            'end_date'   => null,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/goals', [
                'start_date' => '2026-02-01',
                'kcal'       => 2000,
                'protein_g'  => 160,
                'fat_g'      => 70,
                'carbs_g'    => 200,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('goals', [
            'id'       => $old->id,
            'end_date' => '2026-01-31',
        ]);
    }

    public function test_list_goals(): void
    {
        $user = User::factory()->create();
        Goal::factory()->count(3)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/goals')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_update_goal(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/goals/{$goal->uuid}", [
                'start_date' => $goal->start_date->toDateString(),
                'kcal'       => 1900,
                'protein_g'  => 160,
                'fat_g'      => 65,
                'carbs_g'    => 160,
            ])
            ->assertOk()
            ->assertJsonPath('data.kcal', 1900);
    }

    public function test_delete_goal(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/goals/{$goal->uuid}")
            ->assertNoContent();

        $this->assertDatabaseMissing('goals', ['id' => $goal->id]);
    }

    public function test_cannot_access_other_users_goals(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1)
            ->getJson('/api/v1/goals')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($user1)
            ->deleteJson("/api/v1/goals/{$goal->uuid}")
            ->assertNotFound();
    }

    public function test_goal_validates_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/goals', [
                'start_date' => 'bad',
                'kcal'       => 100,
                'protein_g'  => -1,
                'fat_g'      => 1000,
                'carbs_g'    => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'kcal', 'protein_g', 'fat_g']);
    }

    public function test_goals_require_auth(): void
    {
        $this->getJson('/api/v1/goals')->assertUnauthorized();
        $this->postJson('/api/v1/goals', [])->assertUnauthorized();
    }
}
