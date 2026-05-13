<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_endpoint_is_disabled(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name'        => 'Test User',
            'email'       => 'test@example.com',
            'password'    => 'password123',
            'device_name' => 'test-device',
        ])->assertStatus(405);

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_users_create_command_creates_account(): void
    {
        $this->artisan('users:create', [
            'email'      => 'kirill@example.com',
            'name'       => 'Kirill',
            '--password' => 'secret-pass',
            '--timezone' => 'Europe/Moscow',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'kirill@example.com',
            'name'  => 'Kirill',
            'role'  => 'user',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'       => 'kirill@example.com',
            'password'    => 'secret-pass',
            'device_name' => 'iphone',
        ])->assertOk();
    }

    public function test_users_create_admin_flag_grants_admin_role(): void
    {
        $this->artisan('users:create', [
            'email'      => 'admin@example.com',
            'name'       => 'Admin',
            '--password' => 'secret-pass',
            '--admin'    => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'role'  => 'admin',
        ]);
    }

    public function test_users_create_command_rejects_short_password(): void
    {
        $this->artisan('users:create', [
            'email'      => 'x@example.com',
            'name'       => 'X',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_users_create_command_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->artisan('users:create', [
            'email'      => 'dup@example.com',
            'name'       => 'Dup',
            '--password' => 'long-enough-pwd',
        ])->assertFailed();
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'       => $user->email,
            'password'    => 'secret123',
            'device_name' => 'test-device',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'email'       => $user->email,
            'password'    => 'wrongpassword',
            'device_name' => 'test',
        ])->assertStatus(422);
    }

    public function test_me_returns_current_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_deletes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_can_update_own_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)
            ->putJson('/api/v1/auth/me', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_update_me_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/auth/me', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_me_does_not_change_email(): void
    {
        $user = User::factory()->create(['email' => 'orig@example.com']);

        $this->actingAs($user)
            ->putJson('/api/v1/auth/me', ['name' => 'X', 'email' => 'evil@example.com'])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'orig@example.com']);
    }

    public function test_update_me_requires_auth(): void
    {
        $this->putJson('/api/v1/auth/me', ['name' => 'x'])->assertUnauthorized();
    }
}
