<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Provider
    |--------------------------------------------------------------------------
    |
    | The default provider used when no explicit provider is selected.
    |
    */

    'default' => env('PAYMENT_DEFAULT_PROVIDER', 'fedapay'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Each provider has its own configuration block. Secrets must be
    | stored in .env and never committed to version control.
    |
    */

    'providers' => [

        'fedapay' => [
            'enabled' => env('FEDAPAY_ENABLED', false),
            'api_key' => env('FEDAPAY_API_KEY'),
            'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
            'callback_url' => env('FEDAPAY_CALLBACK_URL'),
            'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),
        ],

        'mtn_momo' => [
            'enabled' => env('MTN_MOMO_ENABLED', false),
            'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
            'user_id' => env('MTN_MOMO_USER_ID'),
            'api_key' => env('MTN_MOMO_API_KEY'),
            'environment' => env('MTN_MOMO_ENVIRONMENT', 'sandbox'),
            'callback_url' => env('MTN_MOMO_CALLBACK_URL'),
            'target_environment' => env('MTN_MOMO_TARGET_ENVIRONMENT', 'mtncameroon'),
            'currency' => env('MTN_MOMO_CURRENCY', 'XAF'),
            'webhook_secret' => env('MTN_MOMO_WEBHOOK_SECRET'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Method → Provider Mapping
    |--------------------------------------------------------------------------
    |
    | Maps payment methods to their default provider. When the client
    | specifies a method without an explicit provider, this mapping
    | determines which provider handles the request.
    |
    */

    'method_mapping' => [
        'mobile_money' => [
            'mtn_momo' => 'mtn_momo',
            'fedapay' => 'fedapay',
        ],
        'card' => 'fedapay',
        'wallet' => 'fedapay',
        'cash' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Expiration
    |--------------------------------------------------------------------------
    |
    | How long a payment can remain pending before it is considered expired.
    | This is separate from the reservation hold expiration.
    |
    */

    'expiration' => [
        'payment_timeout_minutes' => (int) env('PAYMENT_TIMEOUT_MINUTES', 15),
    ],

];
