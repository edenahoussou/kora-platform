<?php

namespace App\Modules\IdentityAccess\Data;

/**
 * Ce que l'application OPS Mobile doit faire à la suite d'un refus : renvoyé
 * dans `error.action` pour que la redirection soit déterministe, sans
 * interpréter le message ni le code HTTP.
 */
enum OpsSessionAction: string
{
    /** Session terminée : retour à l'écran de connexion. */
    case Reauthenticate = 'reauthenticate';

    /** Connecté mais session absente ou liée à un autre terminal : refaire le handshake. */
    case OpenSession = 'open_session';

    /** Refus durable : seule l'agence peut débloquer la situation. */
    case ContactAgency = 'contact_agency';

    /** Refus temporaire : réessayer plus tard. */
    case RetryLater = 'retry_later';
}
