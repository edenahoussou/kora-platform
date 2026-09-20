<?php

namespace App\Modules\Departures\Exceptions;

use App\Modules\Departures\Data\DepartureStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Refus d'un changement de statut ou d'un retard demandé depuis le terminal.
 * Se rend en JSON `{ error: { code, message } }` : le mobile s'appuie sur le
 * code, jamais sur le texte.
 */
class DepartureProgressException extends RuntimeException
{
    public const string INVALID_TRANSITION = 'invalid_transition';

    public const string INVALID_DELAY = 'invalid_delay';

    public const string NOT_ASSIGNED = 'departure_not_assigned';

    private function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status,
    ) {
        parent::__construct($message);
    }

    public static function invalidTransition(DepartureStatus $from, DepartureStatus $to): self
    {
        return new self(
            self::INVALID_TRANSITION,
            sprintf('Transition impossible : %s → %s.', $from->label(), $to->label()),
            422,
        );
    }

    public static function invalidDelay(string $message): self
    {
        return new self(self::INVALID_DELAY, $message, 422);
    }

    public static function notAssigned(): self
    {
        return new self(self::NOT_ASSIGNED, 'Ce départ ne vous est pas affecté.', 403);
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
