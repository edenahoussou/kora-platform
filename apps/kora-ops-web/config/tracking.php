<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Suivi de la flotte (MEZ-100)
    |--------------------------------------------------------------------------
    |
    | Une position n'est jamais rejetée pour cause de faible précision — elle
    | est conservée et marquée `degraded`. En revanche, un horodatage
    | impossible (trop dans le futur ou trop ancien) est rejeté : il ne
    | reflète pas une position réelle du terminal.
    |
    */

    // Tolérance d'horloge : une position ne peut pas être "dans le futur"
    // au-delà de cette marge (dérive d'horloge du terminal).
    'max_clock_skew_seconds' => (int) env('TRACKING_MAX_CLOCK_SKEW_SECONDS', 120),

    // Une position plus ancienne que ça (par rapport à maintenant) est
    // rejetée : trop vieille pour être utile, probablement une purge de
    // file locale plutôt qu'un vrai retard réseau.
    'max_position_age_minutes' => (int) env('TRACKING_MAX_POSITION_AGE_MINUTES', 180),

    // Au-delà de cette précision (mètres), une position est acceptée mais
    // marquée `degraded` plutôt que `accurate`.
    'degraded_accuracy_meters' => (float) env('TRACKING_DEGRADED_ACCURACY_METERS', 75),

    // Une position plus vieille que ça (par rapport à maintenant) fait
    // passer l'état affiché de `live` à `stale` sur fleet/tracking.
    'stale_after_seconds' => (int) env('TRACKING_STALE_AFTER_SECONDS', 90),

    // Terminal considéré hors ligne si aucune activité (position ou heartbeat
    // ops.session) depuis ce délai.
    'terminal_offline_after_seconds' => (int) env('TRACKING_TERMINAL_OFFLINE_AFTER_SECONDS', 300),

    // TTL du cache de lecture rapide (dernière position par session).
    'cache_ttl_seconds' => (int) env('TRACKING_CACHE_TTL_SECONDS', 600),

    // Nombre maximal de positions acceptées par lot d'ingestion.
    'max_positions_per_batch' => (int) env('TRACKING_MAX_POSITIONS_PER_BATCH', 200),

];
