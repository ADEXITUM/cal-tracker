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
                'gender'     => 'male',
                'birth_date' => '1992-05-15',
                'height_cm'  => 180,
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
            'gender'     => 'male',
            'birth_date' => '1992-05-15',
            'height_cm'  => 180,
        ]);

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'gender'     => 'male',
                'birth_date' => '1992-05-15',
                'height_cm'  => 182,
            ])
            ->assertOk()
            ->assertJsonPath('data.height_cm', 182);
    }

    public function test_profile_validates_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'gender'     => 'robot',
                'birth_date' => 'not-a-date',
                'height_cm'  => 50,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gender', 'birth_date', 'height_cm']);
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
            'gender'     => 'female',
            'birth_date' => '1990-01-01',
            'height_cm'  => 165,
        ]);

        $this->actingAs($user1)
            ->getJson('/api/v1/profile')
            ->assertNotFound();
    }
}
