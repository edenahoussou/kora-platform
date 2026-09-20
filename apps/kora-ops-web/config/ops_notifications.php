<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alertes opérationnelles boarding (MEZ-96)
    |--------------------------------------------------------------------------
    |
    | Les notifications sont d'abord persistées (récupérables à tout moment par
    | l'API), puis publiées en temps réel via la couche Broadcasting de Laravel
    | — sans fournisseur imposé : `BROADCAST_CONNECTION` choisit le pilote.
    |
    | Les alertes (terminal révoqué, conflit de scan, opération rejetée,
    | synchronisation échouée, erreur d'impression) déclenchent en plus un
    | e-mail : directeurs des opérations de l'agence du convoyeur en
    | destinataires, super-administrateurs en copie.
    |
    */

    'mail' => [
        'enabled' => (bool) env('OPS_NOTIFICATION_MAIL_ENABLED', true),

        // Nouvelles tentatives d'envoi (secondes entre deux essais).
        'tries' => (int) env('OPS_NOTIFICATION_MAIL_TRIES', 3),
        'backoff' => [60, 300, 900],
    ],

    'pagination' => [
        'default' => 30,
        'max' => 100,
    ],

    // Durée pendant laquelle « manifeste périmé » n'est recalculé qu'une fois
    // par départ (plusieurs ventes rapprochées = une seule notification).
    'manifest_stale_debounce_seconds' => (int) env('OPS_NOTIFICATION_MANIFEST_DEBOUNCE_SECONDS', 120),

];
