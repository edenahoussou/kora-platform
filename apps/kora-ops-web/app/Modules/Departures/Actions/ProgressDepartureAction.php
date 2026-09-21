<?php

namespace App\Modules\Departures\Actions;

use App\Models\User;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Exceptions\DepartureProgressException;
use App\Modules\Departures\Models\Departure;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Progression d'un départ déclarée depuis le terminal : embarquement
 * commencé, bus parti, arrivé. Réutilise les transitions de
 * ChangeDepartureStatusAction (les mêmes que sur OPS Web) et enregistre
 * l'heure réelle (heure locale du terminal, sinon heure de réception).
 *
 * Idempotent : redemander le statut déjà atteint est un succès sans effet
 * (rejeu hors ligne) ; une transition invalide lève
 * DepartureProgressException (`invalid_transition`).
 */
class ProgressDepartureAction
{
    public function __construct(private ChangeDepartureStatusAction $changeStatus) {}

    public function execute(User $actor, Departure $departure, DepartureStatus $target, ?CarbonInterface $occurredAt = null): Departure
    {
        $column = match ($target) {
            DepartureStatus::Boarding => 'boarding_started_at',
            DepartureStatus::Departed => 'departed_at',
            DepartureStatus::Arrived => 'arrived_at',
            default => null,
        };

        return DB::transaction(function () use ($actor, $departure, $target, $occurredAt, $column): Departure {
            $locked = Departure::query()->whereKey($departure->getKey())->lockForUpdate()->firstOrFail();
            $current = DepartureStatus::from((string) $locked->getRawOriginal('status'));

            if ($current === $target) {
                return $locked;
            }

            if ($column === null || ! $this->changeStatus->canTransition($current, $target)) {
                throw DepartureProgressException::invalidTransition($current, $target);
            }

            $updated = $this->changeStatus->execute($locked, $target, [$column => $occurredAt ?? now()]);

            activity('departures')
                ->performedOn($updated)
                ->causedBy($actor)
                ->event('status_changed')
                ->withProperties([
                    'departure_uuid' => $updated->uuid,
                    'from' => $current->value,
                    'to' => $target->value,
                    'occurred_at' => ($occurredAt ?? now())->toISOString(),
                ])
                ->log('Statut du départ mis à jour depuis le terminal');

            return $updated;
        });
    }
}
