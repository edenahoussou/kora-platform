<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Carnet de bord OPS Mobile — e-mail de dépense de voyage
    |--------------------------------------------------------------------------
    |
    | Chaque dépense saisie par l'équipage (carburant, péage, taxe frontière)
    | part en « attente de validation » et déclenche un e-mail détaillé :
    |  - destinataires : directeurs des opérations de l'agence de la dépense
    |    + les adresses de `mail_to` (ex. boîte ops du bureau) ;
    |  - copie : super-administrateurs + les adresses de `mail_cc`.
    | Sans aucun destinataire « ops », les administrateurs passent en
    | destinataires. Listes séparées par des virgules.
    |
    */

    'mail_enabled' => (bool) env('TRIP_EXPENSE_MAIL_ENABLED', true),

    'mail_to' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRIP_EXPENSE_MAIL_TO', ''))))),

    'mail_cc' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRIP_EXPENSE_MAIL_CC', ''))))),

    // Joindre la photo du ticket à l'e-mail (en plus du lien vers OPS Web).
    'attach_evidence' => (bool) env('TRIP_EXPENSE_MAIL_ATTACH_EVIDENCE', true),

];
