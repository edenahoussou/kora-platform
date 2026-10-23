<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\SeatLayout;
use App\Modules\BusNetwork\Models\BusComposition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSeatLayoutAction
{
    public function execute(BusComposition $composition, string $name, ?string $description, SeatLayout $layout): BusComposition
    {
        return DB::transaction(function () use ($composition, $name, $description, $layout): BusComposition {
            $composition = BusComposition::query()->whereKey($composition->id)->lockForUpdate()->firstOrFail();

            if ($composition->isLocked()) {
                throw ValidationException::withMessages([
                    'grid' => 'Ce gabarit est utilisé par au moins un bus : dupliquez-le pour le faire évoluer.',
                ]);
            }

            $composition->update([
                'name' => $name,
                'description' => $description,
                'seat_count' => $layout->seatCount(),
                'grid' => $layout->toArray(),
                'layout' => $layout->toLegacyLayout(),
            ]);

            return $composition->refresh();
        });
    }
}
