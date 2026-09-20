<?php

namespace App\Modules\FleetLite\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Refus d'un signalement d'incident. Se rend en JSON `{ error: { code, message } }`.
 */
class IncidentException extends RuntimeException
{
    public const string NOT_ASSIGNED = 'departure_not_assigned';

    public const string UUID_CONFLICT = 'incident_uuid_conflict';

    private function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status,
    ) {
        parent::__construct($message);
    }

    /** Départ inconnu ou non affecté : même réponse, l'existence d'un autre départ n'est jamais révélée. */
    public static function notAssigned(): self
    {
        return new self(self::NOT_ASSIGNED, 'Ce départ ne vous est pas affecté.', 403);
    }

    /** Cet identifiant d'incident a déjà été utilisé par un autre utilisateur. */
    public static function uuidConflict(): self
    {
        return new self(self::UUID_CONFLICT, 'Cet identifiant d’incident est déjà utilisé.', 409);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => ['code' => $this->errorCode, 'message' => $this->getMessage()],
        ], $this->status);
    }
}
