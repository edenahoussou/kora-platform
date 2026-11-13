<?php

namespace App\Modules\Departures\Actions;

use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Wallet\Jobs\CreditDepartureLoyaltyJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeDepartureStatusAction
{
    /** @var array<string, array<int, DepartureStatus>> */
    private const ALLOWED_TRANSITIONS = [
        'scheduled' => [DepartureStatus::Boarding, DepartureStatus::Cancelled],
        'boarding' => [DepartureStatus::Departed, DepartureStatus::Cancelled],
        'departed' => [DepartureStatus::Arrived],
        'arrived' => [],
        'cancelled' => [],
    ];

    public function canTransition(DepartureStatus $from, DepartureStatus $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from->value], true);
    }

    /**
     * @param  array<string, mixed>  $attributes  Colonnes à écrire avec le statut, en une seule sauvegarde
     *                                            (ex. `departed_at`, posée avant que la notification « bus parti » ne parte).
     */
    public function execute(Departure $departure, DepartureStatus $targetStatus, array $attributes = []): Departure
    {
        return DB::transaction(function () use ($departure, $targetStatus, $attributes): Departure {
            $departure = Departure::query()->lockForUpdate()->findOrFail($departure->id);
            $current = $departure->status;

            if (! $this->canTransition($current, $targetStatus)) {
                throw ValidationException::withMessages([
                    'status' => "Transition invalide : {$current->label()} → {$targetStatus->label()}.",
                ]);
            }

            if ($targetStatus === DepartureStatus::Departed) {
                $attributes['departed_at'] ??= now();
            }
            $departure->update(['status' => $targetStatus, ...$attributes]);

            if ($targetStatus === DepartureStatus::Arrived) {
                CreditDepartureLoyaltyJob::dispatch((int) $departure->getKey())->afterCommit();
            }

            return $departure->refresh();
        });
    }
}
