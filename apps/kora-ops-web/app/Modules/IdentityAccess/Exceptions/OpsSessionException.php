<?php

namespace App\Modules\IdentityAccess\Exceptions;

use App\Modules\IdentityAccess\Data\OpsSessionErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Refus d'une session OPS Mobile (ouverture, renouvellement ou accès à une
 * route OPS). Se rend elle-même : `{ error: { code, message, action, reason? } }`
 * avec le statut HTTP propre au code (401 = session à refaire, 403 = refus
 * d'accès), sans aucun identifiant numérique interne.
 */
class OpsSessionException extends RuntimeException
{
    public function __construct(
        public readonly OpsSessionErrorCode $errorCode,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($errorCode->message());
    }

    public function functionalCode(): string
    {
        return $this->errorCode->value;
    }

    /**
     * Ces refus sont attendus et déjà journalisés dans l'activity log
     * (`ops_session_refused`) : inutile de les répéter dans laravel.log.
     */
    public function report(): void {}

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => array_filter([
                'code' => $this->errorCode->value,
                'message' => $this->errorCode->message(),
                'action' => $this->errorCode->action()->value,
                'reason' => $this->reason,
            ], fn (?string $value): bool => $value !== null),
        ], $this->errorCode->httpStatus());
    }
}
