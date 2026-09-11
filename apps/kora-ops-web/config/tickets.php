<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ticket QR Code Signing Keys (Ed25519)
    |--------------------------------------------------------------------------
    |
    | Ed25519 asymmetric keypair used to sign ticket QR payloads so the KORA
    | P501 boarding app can verify tickets offline.
    |
    | The private key signs on the server. The public key is shared with
    | the boarding app and can be distributed openly — even if it leaks,
    | no one can forge tickets without the private key.
    |
    | Generate a new keypair with:
    |   php -r '$kp=sodium_crypto_sign_keypair(); echo "PRIVATE=".base64_encode(sodium_crypto_sign_secretkey($kp))."\n"; echo "PUBLIC=".base64_encode(sodium_crypto_sign_publickey($kp))."\n";'
    |
    | Export the public key for the P501 app with:
    |   php artisan tickets:public-key
    |
    */

    'signing_private_key' => env('KORA_TICKET_SIGNING_PRIVATE_KEY'),
    'signing_public_key' => env('KORA_TICKET_SIGNING_PUBLIC_KEY'),

];
