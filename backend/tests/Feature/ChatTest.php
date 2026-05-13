<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Meal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.api_key' => 'test-key']);
        config(['services.anthropic.model' => 'claude-sonnet-4-6']);
        config(['services.anthropic.max_tokens' => 2048]);
        config(['services.anthropic.version' => '2023-06-01']);
    }

    private function admin(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => User::ROLE_ADMIN], $overrides));
    }

    private function regularUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => User::ROLE_USER], $overrides));
    }

    public function test_chat_index_requires_auth(): void
    {
        $this->getJson('/api/v1/chat/messages')->assertUnauthorized();
    }

    public function test_chat_send_requires_auth(): void
    {
        $this->postJson('/api/v1/chat/messages', ['text' => 'hi'])->assertUnauthorized();
    }

    public function test_non_admin_cannot_use_chat(): void
    {
        $plain = $this->regularUser();

        $this->actingAs($plain)
            ->getJson('/api/v1/chat/messages')
            ->assertForbidden();

        $this->actingAs($plain)
            ->postJson('/api/v1/chat/messages', ['text' => 'hi'])
            ->assertForbidden();

        $this->actingAs($plain)
            ->postJson('/api/v1/chat/messages/some-uuid/apply', [
                'items' => [['tool_use_id' => 'x', 'action' => 'approve']],
            ])
            ->assertForbidden();
    }

    public function test_chat_send_validates_text(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/chat/messages', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text']);
    }

    public function test_assistant_text_only_response_is_persisted(): void
    {
        $user = $this->admin(['name' => 'Kirill']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Сколько грамм? Я не понял из сообщения.'],
                ],
                'stop_reason' => 'end_turn',
                'usage'       => ['input_tokens' => 100, 'output_tokens' => 20],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/chat/messages', ['text' => 'добавь курицы'])
            ->assertCreated();

        $this->assertDatabaseCount('chat_messages', 2);
        $response
            ->assertJsonPath('data.user.role', 'user')
            ->assertJsonPath('data.user.sender.uuid', $user->uuid)
            ->assertJsonPath('data.assistant.role', 'assistant')
            ->assertJsonPath('data.assistant.sender', null)
            ->assertJsonPath('data.assistant.content.0.type', 'text');

        // Default config: anthropic auth style, x-api-key header.
        Http::assertSent(fn ($req) =>
            $req->hasHeader('x-api-key', 'test-key')
            && $req->hasHeader('anthropic-version')
            && str_starts_with($req->url(), 'https://api.anthropic.com/'),
        );
    }

    public function test_openrouter_style_sends_bearer_to_alternate_base_url(): void
    {
        config(['services.anthropic.api_base'   => 'https://openrouter.ai/api']);
        config(['services.anthropic.auth_style' => 'bearer']);
        config(['services.anthropic.model'      => 'anthropic/claude-sonnet-4.6']);

        $user = $this->admin();

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'content'     => [['type' => 'text', 'text' => 'ok']],
                'stop_reason' => 'end_turn',
                'usage'       => ['input_tokens' => 10, 'output_tokens' => 2],
            ], 200),
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/chat/messages', ['text' => 'hi'])
            ->assertCreated();

        Http::assertSent(fn ($req) =>
            $req->hasHeader('authorization', 'Bearer test-key')
            && !$req->hasHeader('x-api-key')
            && str_starts_with($req->url(), 'https://openrouter.ai/api/v1/messages')
            && ($req->data()['model'] ?? null) === 'anthropic/claude-sonnet-4.6',
        );
    }

    public function test_assistant_tool_use_persists_pending_proposal(): void
    {
        $user = $this->admin(['name' => 'Kirill', 'timezone' => 'UTC']);

        Http::fakeSequence('api.anthropic.com/*')
            ->push([
                'content' => [[
                    'type'  => 'tool_use',
                    'id'    => 'toolu_1',
                    'name'  => 'propose_meal',
                    'input' => [
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
                    ],
                ]],
                'stop_reason' => 'tool_use',
                'usage'       => ['input_tokens' => 100, 'output_tokens' => 50],
            ])
            ->push([
                'content' => [['type' => 'text', 'text' => 'Готово, проверь и подтверди.']],
                'stop_reason' => 'end_turn',
                'usage'       => ['input_tokens' => 150, 'output_tokens' => 15],
            ]);

        $this->actingAs($user)
            ->postJson('/api/v1/chat/messages', ['text' => 'добавь мне 200г грудки'])
            ->assertCreated()
            ->assertJsonPath('data.assistant.content.0.type', 'tool_use')
            ->assertJsonPath('data.assistant.content.0.status', 'pending')
            ->assertJsonPath('data.assistant.content.0.result.kcal', 330.0)
            ->assertJsonPath('data.assistant.content.1.type', 'text');
    }

    public function test_approve_proposal_can_target_another_admin(): void
    {
        $kirill = $this->admin(['name' => 'Kirill']);
        $masha  = $this->admin(['name' => 'Masha']);

        $assistant = ChatMessage::create([
            'sender_user_id' => null,
            'role'           => 'assistant',
            'content' => [[
                'type'   => 'tool_use',
                'id'     => 'toolu_x',
                'name'   => 'propose_meal',
                'input'  => [],
                'status' => 'pending',
                'result' => [
                    'target_user'       => ['uuid' => $masha->uuid, 'name' => 'Masha'],
                    'label'             => 'Рис варёный',
                    'eaten_grams'       => 100,
                    'total_yield_grams' => 100,
                    'kcal'              => 130,
                    'protein_g'         => 2.7,
                    'fat_g'             => 0.3,
                    'carbs_g'           => 28,
                    'slot'              => 'lunch',
                    'eaten_at'          => '2026-05-13T13:00:00+00:00',
                ],
            ]],
        ]);

        $this->actingAs($kirill)
            ->postJson("/api/v1/chat/messages/{$assistant->uuid}/apply", [
                'items' => [['tool_use_id' => 'toolu_x', 'action' => 'approve']],
            ])
            ->assertOk()
            ->assertJsonPath('data.content.0.status', 'approved');

        $this->assertDatabaseHas('meals', [
            'user_id' => $masha->id,
            'name'    => 'Рис варёный',
            'kcal'    => 130,
        ]);
        $this->assertDatabaseMissing('meals', ['user_id' => $kirill->id]);
    }

    public function test_either_admin_can_apply_any_message_in_shared_chat(): void
    {
        $kirill = $this->admin(['name' => 'Kirill']);
        $masha  = $this->admin(['name' => 'Masha']);

        // Kirill typed something that resulted in this assistant proposal.
        $assistant = ChatMessage::create([
            'sender_user_id' => null,
            'role'           => 'assistant',
            'content' => [[
                'type'   => 'tool_use',
                'id'     => 'toolu_share',
                'name'   => 'propose_meal',
                'input'  => [],
                'status' => 'pending',
                'result' => [
                    'target_user'       => ['uuid' => $kirill->uuid, 'name' => 'Kirill'],
                    'label'             => 'X',
                    'eaten_grams'       => 100, 'total_yield_grams' => 100,
                    'kcal' => 100, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 0,
                    'slot' => 'other',
                    'eaten_at' => '2026-05-13T13:00:00+00:00',
                ],
            ]],
        ]);

        // Masha (a different admin) approves it — should succeed.
        $this->actingAs($masha)
            ->postJson("/api/v1/chat/messages/{$assistant->uuid}/apply", [
                'items' => [['tool_use_id' => 'toolu_share', 'action' => 'approve']],
            ])
            ->assertOk();

        $this->assertDatabaseHas('meals', ['user_id' => $kirill->id]);
    }

    public function test_reject_proposal_does_not_create_meal(): void
    {
        $user = $this->admin();
        $assistant = ChatMessage::create([
            'sender_user_id' => null,
            'role'           => 'assistant',
            'content' => [[
                'type'   => 'tool_use',
                'id'     => 'toolu_y',
                'name'   => 'propose_meal',
                'input'  => [],
                'status' => 'pending',
                'result' => [
                    'target_user'       => ['uuid' => $user->uuid, 'name' => $user->name],
                    'label'             => 'X',
                    'eaten_grams'       => 100, 'total_yield_grams' => 100,
                    'kcal' => 100, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 0,
                    'slot' => 'other',
                    'eaten_at' => '2026-05-13T13:00:00+00:00',
                ],
            ]],
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/chat/messages/{$assistant->uuid}/apply", [
                'items' => [['tool_use_id' => 'toolu_y', 'action' => 'reject']],
            ])
            ->assertOk()
            ->assertJsonPath('data.content.0.status', 'rejected');

        $this->assertSame(0, Meal::count());
    }

    public function test_double_apply_is_idempotent(): void
    {
        $user = $this->admin();
        $assistant = ChatMessage::create([
            'sender_user_id' => null,
            'role'           => 'assistant',
            'content' => [[
                'type'   => 'tool_use',
                'id'     => 'toolu_z',
                'name'   => 'propose_meal',
                'input'  => [],
                'status' => 'pending',
                'result' => [
                    'target_user'       => ['uuid' => $user->uuid, 'name' => $user->name],
                    'label'             => 'X',
                    'eaten_grams'       => 100, 'total_yield_grams' => 100,
                    'kcal' => 100, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 0,
                    'slot' => 'other',
                    'eaten_at' => '2026-05-13T13:00:00+00:00',
                ],
            ]],
        ]);

        $payload = ['items' => [['tool_use_id' => 'toolu_z', 'action' => 'approve']]];

        $this->actingAs($user)
            ->postJson("/api/v1/chat/messages/{$assistant->uuid}/apply", $payload)
            ->assertOk();
        $this->actingAs($user)
            ->postJson("/api/v1/chat/messages/{$assistant->uuid}/apply", $payload)
            ->assertOk();

        $this->assertSame(1, Meal::count());
    }

    public function test_shared_chat_index_returns_messages_from_all_admins(): void
    {
        $kirill = $this->admin(['name' => 'Kirill']);
        $masha  = $this->admin(['name' => 'Masha']);

        ChatMessage::create([
            'sender_user_id' => $kirill->id,
            'role'           => 'user',
            'content'        => [['type' => 'text', 'text' => 'from kirill']],
        ]);
        ChatMessage::create([
            'sender_user_id' => $masha->id,
            'role'           => 'user',
            'content'        => [['type' => 'text', 'text' => 'from masha']],
        ]);

        $response = $this->actingAs($kirill)
            ->getJson('/api/v1/chat/messages')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $names = array_map(
            fn (array $m) => $m['sender']['name'] ?? null,
            $response->json('data'),
        );
        $this->assertEqualsCanonicalizing(['Kirill', 'Masha'], $names);
    }
}
