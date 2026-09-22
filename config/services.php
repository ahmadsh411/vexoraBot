<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ============================================================
    //  Telegram
    // ============================================================
    'telegram' => [
        'bot_token'         => env('TELEGRAM_BOT_TOKEN'),
        'bot_username'      => env('TELEGRAM_BOT_USERNAME', 'VexoraDeskBot'),
        'users_channel_id'  => env('TELEGRAM_USERS_CHANNEL_ID'),


        'transactions_channel_id' => env('TELEGRAM_TRANSACTIONS_CHANNEL_ID'),
        'general_channel_id'      => env('TELEGRAM_GENERAL_CHANNEL_ID'),
        'main_channel_id'   => env('TELEGRAM_MAIN_CHANNEL_ID'),
        'main_channel_link' => env('TELEGRAM_MAIN_CHANNEL_LINK', 'https://t.me/+SwaqXxyD-fgyZjg0'),
        'webapp_url'        => env('TELEGRAM_WEBAPP_URL'),
    ],

    // ============================================================
    //  Website
    // ============================================================
    'website' => [
        'url' => env('WEBSITE_URL', 'https://ichancy.com'),
    ],

    // ============================================================
    //  Payment Gateway
    // ============================================================
    'payment_gateway' => [
        'driver'   => env('PAYMENT_GATEWAY_DRIVER', 'live'),
        'base_url' => env('PAYMENT_GATEWAY_URL', 'https://apisyria.com/api/v1'),
        'api_key'  => env('PAYMENT_GATEWAY_API_KEY'),
        'timeout'  => env('PAYMENT_GATEWAY_TIMEOUT', 15),

        // ✅ سيرياتيل PIN
        'syriatel_pin' => env('PAYMENT_GATEWAY_SYRIATEL_PIN'),

        // ✅ هامش التسامح في المبلغ
        'amount_tolerance_percent' => env('PAYMENT_GATEWAY_TOLERANCE', 2),

        'withdraw_delay_min' => (int) env('WITHDRAW_DELAY_MIN', 30),
        'withdraw_delay_max' => (int) env('WITHDRAW_DELAY_MAX', 1800),
    ],

    'ichancy' => [
        'base_url'       => env('ICHANCY_BASE_URL'),
        'agent_username' => env('ICHANCY_AGENT_USERNAME'),
        'agent_password' => env('ICHANCY_AGENT_PASSWORD'),
        'app_key'        => env('ICHANCY_APP_KEY'),
        'email_domain'   => env('ICHANCY_PLAYER_EMAIL_DOMAIN', 'vexora.bot'),
    ],

];
