<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateBusAction
{
    public function __construct(private SyncBusSeatsAction $syncBusSeats) {}

    /** @param array{registration_number: string, bus_model_id: int, composition_id: int, agency_id: int, status: string} $attributes */
    public function execute(Bus $bus, array $attributes): Bus
    {
        return DB::transaction(function () use ($bus, $attributes): Bus {
            $composition = BusComposition::query()->findOrFail($attributes['composition_id']);
            if ($bus->composition_id !== $composition->id && $bus->departures()->exists()) {
                throw ValidationException::withMessages([
                    'composition_id' => 'La composition ne peut pas être modifiée après planification d’un départ.',
                ]);
            }

            $bus->update($attributes + ['seat_count' => $composition->seat_count]);
            if ($bus->wasChanged('composition_id')) {
                $this->syncBusSeats->execute($bus, $composition);
            }

            return $bus->refresh();
        });
    }
}
