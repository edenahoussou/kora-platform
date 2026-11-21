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
            'secret_key' => env('FEDAPAY_SECRET_KEY'),
            'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
            'callback_url' => env('FEDAPAY_CALLBACK_URL'),
            'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),
            'currency' => env('FEDAPAY_CURRENCY', 'XOF'),
            'default_phone_country' => env('FEDAPAY_PHONE_COUNTRY', 'bj'),
            'mobile_money_modes' => [
                'mtn_momo' => env('FEDAPAY_MODE_MTN_MOMO', 'mtn'),
                'moov_flooz' => env('FEDAPAY_MODE_MOOV_FLOOZ', 'moov'),
                'celtiis_cash' => env('FEDAPAY_MODE_CELTIIS_CASH', 'celtiis'),
            ],
        ],

        'mtn_momo' => [
            'enabled' => env('MTN_MOMO_ENABLED', false),
            'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
            'user_id' => env('MTN_MOMO_USER_ID'),
            'api_key' => env('MTN_MOMO_API_KEY'),
            'environment' => env('MTN_MOMO_ENVIRONMENT', 'sandbox'),
            'base_url' => env('MTN_MOMO_BASE_URL'),
            'callback_url' => env('MTN_MOMO_CALLBACK_URL'),
            'target_environment' => env('MTN_MOMO_TARGET_ENVIRONMENT', 'mtnbenin'),
            // Official MTN MoMo sandbox/production target environments. Keep
            // the list explicit so a typo cannot silently target another market.
            'target_environments' => [
                'mtnuganda',
                'mtnghana',
                'mtnivorycoast',
                'mtnzambia',
                'mtncameroon',
                'mtnbenin',
                'mtncongo',
                'mtnswaziland',
                'mtnguineaconakry',
                'mtnsouthafrica',
                'mtnliberia',
                'mtnsouthsudan',
                'mtnnigeria',
                'mtnrwanda',
            ],
            // MTN's public sandbox documentation uses EUR for test calls. The
            // production currency is the currency of the provisioned account.
            'sandbox_currency' => env('MTN_MOMO_SANDBOX_CURRENCY', 'EUR'),
            'production_currency' => env('MTN_MOMO_PRODUCTION_CURRENCY', 'XOF'),
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
