<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\BusNetwork\Models\PhysicalStop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListBusCitiesAction
{
    /**
     * @return Collection<int, array{city: string, country: string, physical_stops: array<int, array{uuid: string, name: string}>}>
     */
    public function handle(): Collection
    {
        return PhysicalStop::query()
            ->select('uuid', 'name', 'city', 'country')
            ->where('status', StopStatus::Active->value)
            ->whereHas('stops', fn (Builder $query): Builder => $query
                ->where('status', StopStatus::Active->value)
                ->whereHas('line', fn (Builder $q): Builder => $q->where('status', 'active')))
            ->orderBy('city')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (PhysicalStop $stop): string => $stop->city)
            ->map(fn (Collection $stops, string $city): array => [
                'city' => $city,
                'country' => (string) $stops->first()->country,
                'physical_stops' => $stops
                    ->map(fn (PhysicalStop $stop): array => [
                        'uuid' => $stop->uuid,
                        'name' => $stop->name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values();
    }
}
