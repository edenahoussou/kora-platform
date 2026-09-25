<?php

namespace App\Modules\IdentityAccess\Exceptions;

use Throwable;

/**
 * Le code OTP n'a pu être délivré sur aucun canal disponible (WhatsApp
 * en échec et aucune adresse email joignable). Renvoyée au client en
 * `503 otp_delivery_unavailable` plutôt que de laisser un code perdu
 * dans le vide — l'utilisateur doit savoir qu'aucun code n'arrivera.
 */
class OtpDeliveryException extends AuthBusinessException
{
    public function __construct(string $message = "Impossible d'envoyer le code. Réessayez plus tard.", ?Throwable $previous = null)
    {
        parent::__construct('otp_delivery_unavailable', $message, 503, $previous);
    }
}
