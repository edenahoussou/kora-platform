<?php

namespace App\Modules\IdentityAccess\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Erreur métier du flux d'authentification, exposée au client sous la
 * forme `{ "error": { "code": …, "message": … } }` avec le statut HTTP
 * porté par l'exception (409 `account_exists`, 422 `phone_taken`…).
 */
class AuthBusinessException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
