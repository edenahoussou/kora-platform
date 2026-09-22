<?php

namespace App\Modules\Tracking\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Refus d'une opération de suivi. Se rend en JSON `{ error: { code, message } }`.
 */
class TrackingException extends RuntimeException
{
    public const string DEPARTURE_NOT_ASSIGNED = 'departure_not_assigned';

    public const string TOO_MANY_POSITIONS = 'too_many_positions';

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
        return new self(self::DEPARTURE_NOT_ASSIGNED, 'Ce départ ne vous est pas affecté.', 403);
    }

    public static function tooManyPositions(int $max): self
    {
        return new self(self::TOO_MANY_POSITIONS, "Un lot ne peut pas contenir plus de {$max} positions.", 422);
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
