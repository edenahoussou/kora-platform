<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\CompositionStatus;
use App\Modules\BusNetwork\Data\SeatLayout;
use App\Modules\BusNetwork\Models\BusComposition;
use Illuminate\Support\Facades\DB;

class CreateSeatLayoutAction
{
    public function __construct(private GenerateSeatLayoutCodeAction $generateCode) {}

    public function execute(string $name, ?string $description, SeatLayout $layout): BusComposition
    {
        return DB::transaction(fn (): BusComposition => BusComposition::query()->create([
            'code' => $this->generateCode->execute(),
            'name' => $name,
            'description' => $description,
            'seat_count' => $layout->seatCount(),
            'grid' => $layout->toArray(),
            'layout' => $layout->toLegacyLayout(),
            'status' => CompositionStatus::Draft->value,
        ]));
    }
}
