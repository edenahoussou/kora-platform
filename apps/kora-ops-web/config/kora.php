<?php

return [

    /*
    |--------------------------------------------------------------------------
    | KORA Service Identity
    |--------------------------------------------------------------------------
    |
    | Metadata exposed by the health endpoint and monitoring tooling. The API
    | version follows the versioned REST prefix (`/api/v1`) and is bumped only
    | when a breaking change ships under a new prefix.
    |
    */

    'service' => env('KORA_SERVICE_NAME', 'kora-platform'),

    'scope' => env('KORA_SCOPE', 'mvp-bus'),

    'api_version' => env('KORA_API_VERSION', 'v1'),

    'departures' => [
        'sellability' => [
            'default_cutoff_minutes' => env('KORA_SALE_CUTOFF_MINUTES', 30),
            'sellable_statuses' => ['scheduled'],
            'stale_threshold_hours' => env('KORA_STALE_DEPARTURE_HOURS', 24),
            'max_delay_minutes' => env('KORA_MAX_DEPARTURE_DELAY_MINUTES', 720),
            'overrides' => [
                'lines' => [],
            ],
        ],
    ],

    'itineraries' => [
        'connection_margin_minutes' => env('KORA_CONNECTION_MARGIN_MINUTES', 45),
        'max_legs' => env('KORA_ITINERARY_MAX_LEGS', 3),
        'max_results' => env('KORA_ITINERARY_MAX_RESULTS', 25),
        'default_search_days' => env('KORA_ITINERARY_DEFAULT_SEARCH_DAYS', 7),
        'max_wait_hours' => env('KORA_CONNECTION_MAX_WAIT_HOURS', 12),
    ],

];
