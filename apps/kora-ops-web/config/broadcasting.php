<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    |
    | Pilote neutre par défaut : `null` (rien n'est diffusé, les notifications
    | restent récupérables par l'API) ou `log` (traces dans les logs). Un
    | fournisseur temps réel (Reverb, Pusher, Ably…) s'ajoute ici en déclarant
    | sa connexion, sans modifier le code applicatif.
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
