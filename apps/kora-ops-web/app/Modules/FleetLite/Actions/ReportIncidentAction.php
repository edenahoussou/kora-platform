<?php

namespace App\Modules\FleetLite\Actions;

use App\Models\User;
use App\Modules\Departures\Models\Departure;
use App\Modules\FleetLite\Data\IncidentStatus;
use App\Modules\FleetLite\Data\IncidentType;
use App\Modules\FleetLite\Exceptions\IncidentException;
use App\Modules\FleetLite\Models\Incident;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Notifications\Services\OpsNotifier;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre un incident signalé depuis le terminal, puis publie l'alerte
 * (notification + e-mail, voir OpsNotifier::incidentReported).
 *
 * Idempotent par `incident_uuid` (généré par le mobile) : rejouer le même
 * signalement — coupure réseau, retry — renvoie l'incident existant sans
 * créer de doublon ni renvoyer d'alerte. Le même identifiant utilisé par un
 * autre utilisateur est refusé (IncidentException::uuidConflict).
 */
class ReportIncidentAction
{
    public function __construct(private OpsNotifier $notifier) {}

    /**
     * @return array{0: Incident, 1: bool} L'incident et `true` s'il vient d'être créé.
     */
    public function execute(
        User $reporter,
        Departure $departure,
        ?TerminalP501 $terminal,
        string $incidentUuid,
        IncidentType $type,
        string $description,
        ?float $latitude,
        ?float $longitude,
        ?CarbonInterface $occurredAt,
    ): array {
        $existing = Incident::withTrashed()->where('uuid', $incidentUuid)->first();

        if ($existing !== null) {
            return $this->replay($existing, $reporter);
        }

        try {
            $incident = DB::transaction(fn (): Incident => Incident::query()->create([
                'uuid' => $incidentUuid,
                'departure_id' => $departure->id,
                'bus_id' => $departure->bus_id,
                'type' => $type,
                'description' => trim($description),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'occurred_at' => $occurredAt ?? now(),
                'created_by' => $reporter->id,
                'terminal_p501_id' => $terminal?->id,
                'status' => IncidentStatus::Open,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Course : le même signalement vient d'être enregistré en parallèle.
            return $this->replay(Incident::withTrashed()->where('uuid', $incidentUuid)->firstOrFail(), $reporter);
        }

        activity('incidents')
            ->performedOn($incident)
            ->causedBy($reporter)
            ->event('reported')
            ->withProperties([
                'incident_uuid' => $incident->uuid,
                'departure_uuid' => $departure->uuid,
                'type' => $type->value,
            ])
            ->log('Incident signalé depuis le terminal');

        $this->notifier->incidentReported($incident, $departure, $reporter, $terminal);

        return [$incident, true];
    }

    /**
     * @return array{0: Incident, 1: bool}
     */
    private function replay(Incident $existing, User $reporter): array
    {
        if ($existing->created_by !== $reporter->id) {
            throw IncidentException::uuidConflict();
        }

        return [$existing, false];
    }
}
