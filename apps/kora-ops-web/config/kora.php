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

];
