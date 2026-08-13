<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\BusNetwork\Models\Stop;
use Illuminate\Support\Collection;

class ListBusCitiesAction
{
    /**
     * @return Collection<int, array{city: string, country: string, stops_count: int}>
     */
    public function handle(): Collection
    {
        return Stop::query()
            ->select('city', 'country')
            ->selectRaw('COUNT(*) as stops_count')
            ->where('status', StopStatus::Active->value)
            ->groupBy('city', 'country')
            ->orderBy('city')
            ->get()
            ->map(fn (Stop $stop): array => [
                'city' => $stop->city,
                'country' => $stop->country,
                'stops_count' => (int) $stop->getAttribute('stops_count'),
            ]);
    }
}
