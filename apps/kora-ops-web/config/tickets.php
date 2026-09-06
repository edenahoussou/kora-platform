<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ticket QR Code Signing Key
    |--------------------------------------------------------------------------
    |
    | HMAC-SHA256 key used to sign ticket QR payloads so the KORA P501
    | boarding app can verify tickets offline. Generate a strong random
    | key (32+ characters) and share it securely with the boarding app.
    |
    */

    'signing_key' => env('KORA_TICKET_SIGNING_KEY'),

];
