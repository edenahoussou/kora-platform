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

    'genuka' => [
        'url' => env('GENUKA_URL', 'https://wa.genuka.com'),
        'token' => env('GENUKA_TOKEN'),
        'connection_id' => env('GENUKA_CONNECTION_ID'),
    ],

    'waha' => [
        'url' => env('WAHA_URL', 'http://waha:3000'),
        'token' => env('WAHA_TOKEN'),
        'session' => env('WAHA_SESSION', 'default'),
        // Délai anti-blocage (secondes) avant le premier envoi vers un contact
        // nouveau. 0 désactive le délai (utile en test). WAHA recommande 30-60s.
        'new_contact_delay_seconds' => env('WAHA_NEW_CONTACT_DELAY_SECONDS', 30),
    ],

    'border_demo' => [
        'conductor_email' => env('BORDER_DEMO_CONDUCTOR_EMAIL', 'conductor.marc@kora.demo'),
        'phone_serial' => env('BORDER_DEMO_PHONE_SERIAL', ''),
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

    /*
    |--------------------------------------------------------------------------
    | Compte de revue App Store / Play Store (MEZ-152)
    |--------------------------------------------------------------------------
    |
    | Le reviewer ne peut pas recevoir nos OTP (WhatsApp/SMS/email). Un code
    | fixe, stocké uniquement en variable d'environnement, est accepté pour
    | les seuls identifiants whitelistés, et uniquement si `enabled` vaut
    | true. Désactivé par défaut : aucun effet hors fenêtre de revue.
    |
    | `identifiers` est une liste CSV (une seule variable d'environnement) :
    |   STORE_REVIEW_IDENTIFIERS=apple-review@koraexpress.app,apple-review+signup@koraexpress.app
    |
    | `login_identifier` désigne LE compte que le seeder crée. Les autres
    | identifiants de la liste servent aux tests d'inscription et ne sont
    | jamais pré-créés (sinon l'inscription renverrait `account_exists`).
    |
    */

    'store_review' => [
        'enabled' => filter_var(env('STORE_REVIEW_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'otp_code' => env('STORE_REVIEW_OTP_CODE'),
        'identifiers' => array_values(array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode(',', (string) env('STORE_REVIEW_IDENTIFIERS', '')),
        ))),
        'login_identifier' => strtolower(trim((string) env('STORE_REVIEW_LOGIN_IDENTIFIER', 'apple-review@koraexpress.app'))),
        'pin' => (string) env('STORE_REVIEW_PIN', '2468'),
    ],

];
