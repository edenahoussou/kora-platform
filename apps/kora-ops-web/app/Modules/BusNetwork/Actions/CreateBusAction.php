<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use Illuminate\Support\Facades\DB;

class CreateBusAction
{
    public function __construct(private SyncBusSeatsAction $syncBusSeats) {}

    /** @param array{registration_number: string, bus_model_id: int, composition_id: int, status: string, amenity_ids?: list<int>} $attributes */
    public function execute(array $attributes): Bus
    {
        return DB::transaction(function () use ($attributes): Bus {
            $amenityIds = $attributes['amenity_ids'] ?? [];
            unset($attributes['amenity_ids']);

            $bus = Bus::query()->create($attributes + [
                'seat_count' => BusComposition::query()->findOrFail($attributes['composition_id'])->seat_count,
            ]);
            $this->syncBusSeats->execute($bus, BusComposition::query()->findOrFail($attributes['composition_id']));
            $bus->amenities()->sync($amenityIds);

            return $bus;
        });
    }
}
