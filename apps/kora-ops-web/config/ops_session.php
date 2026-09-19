<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sessions OPS Mobile (convoyeur ↔ terminal P501)
    |--------------------------------------------------------------------------
    |
    | Une session OPS lie un jeton Sanctum (MEZ-61) à un terminal enregistré et
    | à son numéro de série. Elle a sa propre expiration, distincte de celle du
    | jeton : `ttl_minutes` est la durée d'une fenêtre (prolongée par un
    | renouvellement explicite), `max_lifetime_minutes` la durée absolue depuis
    | l'ouverture, qu'aucun renouvellement ne peut dépasser.
    |
    */

    'ttl_minutes' => (int) env('OPS_SESSION_TTL_MINUTES', 720),

    'max_lifetime_minutes' => (int) env('OPS_SESSION_MAX_LIFETIME_MINUTES', 1440),

    // Intervalle minimal entre deux mises à jour de `last_seen_at` (évite une
    // écriture en base à chaque requête du terminal).
    'touch_interval_seconds' => (int) env('OPS_SESSION_TOUCH_INTERVAL_SECONDS', 60),

];
