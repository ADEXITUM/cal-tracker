<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'fatsecret' => [
        // OAuth 2.0 client_credentials (https://platform.fatsecret.com/docs/guides).
        // Free tier требует whitelisting IP сервера в кабинете FatSecret — без него
        // токен получается, но /rest/server.api отдаёт error code 21.
        'client_id'     => env('FATSECRET_CLIENT_ID'),
        'client_secret' => env('FATSECRET_CLIENT_SECRET'),
        'token_url'     => rtrim((string) env('FATSECRET_TOKEN_URL', 'https://oauth.fatsecret.com/connect/token'), '/'),
        'api_base'      => rtrim((string) env('FATSECRET_API_BASE', 'https://platform.fatsecret.com/rest/server.api'), '/'),
        'scope'         => env('FATSECRET_SCOPE', 'basic'),
    ],

    'anthropic' => [
        // Direct Anthropic uses x-api-key. OpenRouter (and similar
        // passthrough proxies that mirror the Messages API) uses Bearer
        // — see ANTHROPIC_AUTH_STYLE.
        'api_key'    => env('ANTHROPIC_API_KEY'),
        'api_base'   => rtrim((string) env('ANTHROPIC_API_BASE', 'https://api.anthropic.com'), '/'),
        // 'anthropic' → sends x-api-key + anthropic-version (api.anthropic.com)
        // 'bearer'    → sends Authorization: Bearer (OpenRouter passthrough)
        'auth_style' => env('ANTHROPIC_AUTH_STYLE', 'anthropic'),
        'model'      => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 2048),
        'version'    => env('ANTHROPIC_API_VERSION', '2023-06-01'),
    ],

];
