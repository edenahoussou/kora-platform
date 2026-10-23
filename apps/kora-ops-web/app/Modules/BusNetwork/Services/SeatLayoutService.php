<?php

namespace App\Modules\BusNetwork\Services;

use App\Modules\BusNetwork\Actions\ChangeSeatLayoutStatusAction;
use App\Modules\BusNetwork\Actions\CreateSeatLayoutAction;
use App\Modules\BusNetwork\Actions\DuplicateSeatLayoutAction;
use App\Modules\BusNetwork\Actions\UpdateSeatLayoutAction;
use App\Modules\BusNetwork\Data\CompositionStatus;
use App\Modules\BusNetwork\Data\SeatLayout;
use App\Modules\BusNetwork\Models\BusComposition;
use Illuminate\Support\Collection;

class SeatLayoutService
{
    public function __construct(
        private CreateSeatLayoutAction $createSeatLayout,
        private UpdateSeatLayoutAction $updateSeatLayout,
        private DuplicateSeatLayoutAction $duplicateSeatLayout,
        private ChangeSeatLayoutStatusAction $changeSeatLayoutStatus,
    ) {}

    /** @return Collection<int, BusComposition> */
    public function templatesForIndex(): Collection
    {
        return BusComposition::query()->withCount('buses')->orderBy('name')->get();
    }

    /** @param array{name: string, description?: string|null, grid: array<string, mixed>} $payload */
    public function create(array $payload): BusComposition
    {
        return $this->createSeatLayout->execute(
            $payload['name'],
            $payload['description'] ?? null,
            SeatLayout::fromGridArray($payload['grid']),
        );
    }

    /** @param array{name: string, description?: string|null, grid: array<string, mixed>} $payload */
    public function update(BusComposition $composition, array $payload): BusComposition
    {
        return $this->updateSeatLayout->execute(
            $composition,
            $payload['name'],
            $payload['description'] ?? null,
            SeatLayout::fromGridArray($payload['grid']),
        );
    }

    public function duplicate(BusComposition $composition): BusComposition
    {
        return $this->duplicateSeatLayout->execute($composition);
    }

    public function changeStatus(BusComposition $composition, CompositionStatus $status): BusComposition
    {
        return $this->changeSeatLayoutStatus->execute($composition, $status);
    }

    /** @return array{uuid: string, name: string, code: string, description: string|null, status: string, seatCount: int, busesCount: int, locked: bool, updatedAt: string|null, grid: array<string, mixed>} */
    public function serialize(BusComposition $composition): array
    {
        return [
            'uuid' => $composition->uuid,
            'name' => $composition->name,
            'code' => $composition->code,
            'description' => $composition->description,
            'status' => (string) $composition->getRawOriginal('status'),
            'seatCount' => $composition->seat_count,
            'busesCount' => $composition->buses_count ?? $composition->buses()->count(),
            'locked' => $composition->isLocked(),
            'updatedAt' => $composition->updated_at?->toIso8601String(),
            'grid' => $composition->seatGrid()->toArray(),
        ];
    }
}
