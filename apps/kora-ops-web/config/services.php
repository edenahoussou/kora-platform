<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    |--------------------------------------------------------------------------
    | OAuth Social Providers (Laravel Socialite)
    |--------------------------------------------------------------------------
    |
    | Identifiants OAuth 2.0 pour l'authentification sociale de l'app
    | passager KORA (Google, Facebook, TikTok). Le client_id est public
    | (côté mobile), le client_secret reste côté backend et valide les
    | tokens reçus via Socialite::driver($provider)->userFromToken().
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_OAUTH_REDIRECT', '/api/v1/auth/oauth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_OAUTH_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_OAUTH_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_OAUTH_REDIRECT', '/api/v1/auth/oauth/facebook/callback'),
    ],

    'tiktok' => [
        'client_id' => env('TIKTOK_OAUTH_CLIENT_ID'),
        'client_secret' => env('TIKTOK_OAUTH_CLIENT_SECRET'),
        'redirect' => env('TIKTOK_OAUTH_REDIRECT', '/api/v1/auth/oauth/tiktok/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fournisseur cartographique (MEZ — étude carte lignes/arrêts)
    |--------------------------------------------------------------------------
    |
    | Le fournisseur actif est résolu ainsi : réglage admin (MapSetting, table
    | à ligne unique) si défini, sinon cette valeur d'environnement. OSM est le
    | choix par défaut : gratuit, sans clé API, adapté à un lancement rapide.
    | Google Maps reste disponible en bascule pour des besoins ponctuels
    | (Places/geocoding plus précis) moyennant une clé API et la facturation
    | associée — voir docs/map-provider-study.md pour l'arbitrage complet.
    |
    */

    'maps' => [
        'default_provider' => env('MAP_PROVIDER', 'openstreetmap'),

        'google' => [
            'api_key' => env('MAP_GOOGLE_MAPS_API_KEY'),
        ],

        'osm' => [
            'tile_url' => env('MAP_OSM_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
            'attribution' => env('MAP_OSM_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'),
        ],
    ],

];
