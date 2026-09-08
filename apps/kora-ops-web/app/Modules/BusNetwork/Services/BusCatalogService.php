<?php

namespace App\Modules\BusNetwork\Services;

use App\Modules\BusNetwork\Actions\FindLinesForSegmentAction;
use App\Modules\BusNetwork\Actions\ListBusCitiesAction;
use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\BusNetwork\Models\Line;
use Illuminate\Support\Collection;

class BusCatalogService
{
    public function __construct(
        private ListBusCitiesAction $listBusCities,
        private FindLinesForSegmentAction $findLinesForSegment,
    ) {}

    /**
     * @return Collection<int, array{city: string, country: string, physical_stops: array<int, array{uuid: string, name: string}>}>
     */
    public function cities(): Collection
    {
        return $this->listBusCities->handle();
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return Collection<int, Line>
     */
    public function lines(array $filters): Collection
    {
        return $this->findLinesForSegment->handle($filters['from'] ?? null, $filters['to'] ?? null);
    }

    public function line(Line $line): Line
    {
        return $line->load(['stops' => fn ($query) => $query
            ->where('status', StopStatus::Active->value)
            ->orderBy('stop_order')]);
    }
}
