<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FatSecret\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FatSecretClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.fatsecret.client_id', 'test-id');
        config()->set('services.fatsecret.client_secret', 'test-secret');
        config()->set('services.fatsecret.token_url', 'https://oauth.fatsecret.com/connect/token');
        config()->set('services.fatsecret.api_base', 'https://platform.fatsecret.com/rest/server.api');
        config()->set('services.fatsecret.scope', 'basic');
        Cache::flush();
    }

    public function test_search_foods_normalizes_single_and_list_responses(): void
    {
        Http::fake([
            'oauth.fatsecret.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'platform.fatsecret.com/*' => Http::response([
                'foods' => [
                    'food' => [
                        ['food_id' => '1', 'food_name' => 'Apple', 'brand_name' => 'Generic', 'food_description' => 'Per 100g - 52 kcal'],
                        ['food_id' => '2', 'food_name' => 'Banana'],
                    ],
                ],
            ]),
        ]);

        $hits = Client::fromConfig()->searchFoods('apple');

        $this->assertCount(2, $hits);
        $this->assertSame('1', $hits[0]['food_id']);
        $this->assertSame('Apple', $hits[0]['name']);
        $this->assertSame('Generic', $hits[0]['brand']);
        $this->assertSame('Per 100g - 52 kcal', $hits[0]['description']);
        $this->assertSame('Banana', $hits[1]['name']);
        $this->assertNull($hits[1]['brand']);
    }

    public function test_search_wraps_single_object_into_list(): void
    {
        Http::fake([
            'oauth.fatsecret.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'platform.fatsecret.com/*' => Http::response([
                'foods' => [
                    'food' => ['food_id' => '99', 'food_name' => 'Solo result'],
                ],
            ]),
        ]);

        $hits = Client::fromConfig()->searchFoods('solo');
        $this->assertCount(1, $hits);
        $this->assertSame('99', $hits[0]['food_id']);
    }

    public function test_get_food_normalizes_servings_with_grams(): void
    {
        Http::fake([
            'oauth.fatsecret.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'platform.fatsecret.com/*' => Http::response([
                'food' => [
                    'food_id'   => '777',
                    'food_name' => 'Apple',
                    'servings'  => [
                        'serving' => [
                            [
                                'serving_id'            => '1',
                                'serving_description'   => '1 cup',
                                'metric_serving_amount' => '240',
                                'metric_serving_unit'   => 'g',
                                'calories'              => '130',
                                'protein'               => '0.6',
                                'fat'                   => '0.4',
                                'carbohydrate'          => '34',
                            ],
                            [
                                'serving_id'          => '2',
                                'serving_description' => '1 medium',
                                'calories'            => '95',
                                'protein'             => '0.5',
                                'fat'                 => '0.3',
                                'carbohydrate'        => '25',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $food = Client::fromConfig()->getFood('777');

        $this->assertSame('Apple', $food['name']);
        $this->assertCount(2, $food['servings']);
        $this->assertSame(240.0, $food['servings'][0]['grams']);
        $this->assertSame(130.0, $food['servings'][0]['kcal']);
        $this->assertNull($food['servings'][1]['grams']);
        $this->assertSame(95.0, $food['servings'][1]['kcal']);
    }

    public function test_access_token_is_cached_between_calls(): void
    {
        Http::fake([
            'oauth.fatsecret.com/*' => Http::sequence()
                ->push(['access_token' => 'tok-1', 'expires_in' => 3600])
                ->push(['access_token' => 'tok-2', 'expires_in' => 3600]),
            'platform.fatsecret.com/*' => Http::response(['foods' => ['food' => []]]),
        ]);

        $client = Client::fromConfig();
        $client->searchFoods('a');
        $client->searchFoods('b');

        // Должен быть ровно один поход за токеном — второй раз достали из кэша.
        Http::assertSentCount(3); // 1 токен + 2 поиска
        $this->assertSame('tok-1', Cache::get(Client::TOKEN_CACHE_KEY));
    }

    public function test_error_envelope_is_translated_to_exception(): void
    {
        // FatSecret отдаёт 200-OK с {"error": {"code": 21}} при незанесённом IP.
        Http::fake([
            'oauth.fatsecret.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'platform.fatsecret.com/*' => Http::response([
                'error' => ['code' => 21, 'message' => 'IP не разрешён'],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/FatSecret error 21/');
        Client::fromConfig()->searchFoods('apple');
    }

    public function test_401_clears_token_and_retries_once(): void
    {
        Cache::put(Client::TOKEN_CACHE_KEY, 'stale-tok', 60);

        Http::fake([
            'oauth.fatsecret.com/*' => Http::response(['access_token' => 'fresh-tok', 'expires_in' => 3600]),
            'platform.fatsecret.com/*' => Http::sequence()
                ->push(['error' => 'unauthorized'], 401)
                ->push(['foods' => ['food' => []]]),
        ]);

        Client::fromConfig()->searchFoods('apple');
        $this->assertSame('fresh-tok', Cache::get(Client::TOKEN_CACHE_KEY));
    }
}
