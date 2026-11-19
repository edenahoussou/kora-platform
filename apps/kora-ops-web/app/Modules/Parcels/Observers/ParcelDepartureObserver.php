<?php

namespace App\Modules\Parcels\Observers;

use App\Modules\Departures\Models\Departure;
use App\Modules\Parcels\Models\ParcelAssignment;
use App\Modules\Parcels\Services\ParcelTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParcelDepartureObserver
{
    public function updating(Departure $departure): void
    {
        if ($departure->isDirty(['bus_id', 'line_id', 'stop_times'])
            && ParcelAssignment::query()->where('departure_id', $departure->id)->whereNull('released_at')
                ->whereNotNull('loaded_at')->whereNull('unloaded_at')->exists()) {
            throw ValidationException::withMessages(['departure' => 'Déchargez les colis avant de modifier le trajet ou le véhicule.']);
        }
    }

    public function updated(Departure $departure): void
    {
        if (! $departure->wasChanged(['status', 'bus_id', 'line_id', 'stop_times'])) {
            return;
        }

        DB::transaction(function () use ($departure): void {
            $locked = Departure::query()->lockForUpdate()->findOrFail($departure->id);
            $transport = app(ParcelTransport::class);
            if ($departure->wasChanged(['bus_id', 'line_id', 'stop_times'])) {
                $transport->releaseChangedDeparture($locked);
            }
            if ($departure->wasChanged('status')) {
                $transport->departureChanged($locked);
            }
        });
    }
}
