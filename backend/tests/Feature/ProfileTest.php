<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_profile_returns_404_when_no_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/profile')
            ->assertNotFound();
    }

    public function test_create_profile_via_put(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'gender'         => 'male',
                'birth_date'     => '1992-05-15',
                'height_cm'      => 180,
                'activity_level' => 'sedentary',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.gender', 'male')
            ->assertJsonPath('data.height_cm', 180);

        $this->assertDatabaseHas('profiles', ['user_id' => $user->id]);
    }

    public function test_update_profile_via_put(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'gender'         => 'male',
            'birth_date'     => '1992-05-15',
            'height_cm'      => 180,
            'activity_level' => 'sedentary',
        ]);

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'gender'         => 'male',
                'birth_date'     => '1992-05-15',
                'height_cm'      => 182,
                'activity_level' => 'moderate',
            ])
            ->assertOk()
            ->assertJsonPath('data.height_cm', 182)
            ->assertJsonPath('data.activity_level', 'moderate');
    }

    public function test_profile_validates_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'gender'         => 'robot',
                'birth_date'     => 'not-a-date',
                'height_cm'      => 50,
                'activity_level' => 'ultra',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gender', 'birth_date', 'height_cm', 'activity_level']);
    }

    public function test_profile_requires_auth(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->putJson('/api/v1/profile', [])->assertUnauthorized();
    }

    public function test_cannot_access_other_users_profile(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user2->profile()->create([
            'gender'         => 'female',
            'birth_date'     => '1990-01-01',
            'height_cm'      => 165,
            'activity_level' => 'light',
        ]);

        // user1 has no profile — gets 404, not user2's data
        $this->actingAs($user1)
            ->getJson('/api/v1/profile')
            ->assertNotFound();
    }
}
