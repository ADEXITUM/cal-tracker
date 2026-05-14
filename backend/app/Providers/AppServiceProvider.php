<?php

namespace App\Providers;

use App\Services\Anthropic\Client as AnthropicClient;
use App\Services\FatSecret\Client as FatSecretClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AnthropicClient::class, fn () => AnthropicClient::fromConfig());
        $this->app->singleton(FatSecretClient::class, fn () => FatSecretClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
