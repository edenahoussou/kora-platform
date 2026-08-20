<?php

namespace App\Modules\Departures\Actions;

use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
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

    public function execute(Departure $departure, DepartureStatus $targetStatus): Departure
    {
        $current = DepartureStatus::from((string) $departure->getRawOriginal('status'));
        $allowed = self::ALLOWED_TRANSITIONS[$current->value];

        if (! in_array($targetStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Transition invalide : {$current->label()} → {$targetStatus->label()}.",
            ]);
        }

        $departure->update(['status' => $targetStatus]);

        return $departure->refresh();
    }
}
