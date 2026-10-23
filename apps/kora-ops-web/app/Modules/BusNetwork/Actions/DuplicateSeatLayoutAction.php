<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\CompositionStatus;
use App\Modules\BusNetwork\Models\BusComposition;
use Illuminate\Support\Facades\DB;

class DuplicateSeatLayoutAction
{
    public function __construct(private GenerateSeatLayoutCodeAction $generateCode) {}

    public function execute(BusComposition $composition): BusComposition
    {
        return DB::transaction(fn (): BusComposition => BusComposition::query()->create([
            'code' => $this->generateCode->execute(),
            'name' => sprintf('Copie de %s', $composition->name),
            'description' => $composition->description,
            'seat_count' => $composition->seat_count,
            'grid' => $composition->seatGrid()->toArray(),
            'layout' => $composition->seatGrid()->toLegacyLayout(),
            'status' => CompositionStatus::Draft->value,
        ]));
    }
}
