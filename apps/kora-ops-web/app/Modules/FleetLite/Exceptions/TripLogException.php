<?php

namespace App\Modules\FleetLite\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Refus d'une saisie du carnet de bord (kilométrage, carburant, péage,
 * taxe frontière). Se rend en JSON `{ error: { code, message } }`.
 */
class TripLogException extends RuntimeException
{
    public const string NOT_ASSIGNED = 'departure_not_assigned';

    public const string UUID_CONFLICT = 'trip_entry_uuid_conflict';

    public const string ODOMETER_INCONSISTENT = 'odometer_inconsistent';

    public const string AGENCY_UNRESOLVED = 'agency_unresolved';

    private function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status,
    ) {
        parent::__construct($message);
    }

    /** Départ inconnu, annulé ou non affecté : même réponse. */
    public static function notAssigned(): self
    {
        return new self(self::NOT_ASSIGNED, 'Ce départ ne vous est pas affecté.', 403);
    }

    /** Identifiant client déjà utilisé par un autre utilisateur. */
    public static function uuidConflict(): self
    {
        return new self(self::UUID_CONFLICT, 'Cet identifiant de saisie est déjà utilisé.', 409);
    }

    public static function odometerInconsistent(int $start, int $end): self
    {
        return new self(
            self::ODOMETER_INCONSISTENT,
            sprintf('Le kilométrage d’arrivée (%s km) ne peut pas être inférieur à celui du départ (%s km).', number_format($end, 0, ',', ' '), number_format($start, 0, ',', ' ')),
            422,
        );
    }

    public static function agencyUnresolved(): self
    {
        return new self(self::AGENCY_UNRESOLVED, 'Aucune agence n’est rattachée à votre compte. Contactez votre agence.', 422);
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
