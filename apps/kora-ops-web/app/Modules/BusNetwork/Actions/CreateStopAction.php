<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateStopAction
{
    /**
     * @param  array{name: string, city: string, country: string, stop_order: int, km_from_origin?: float, latitude?: float|null, longitude?: float|null}  $attributes
     */
    public function execute(Line $line, array $attributes): Stop
    {
        return DB::transaction(function () use ($line, $attributes): Stop {
            $existingOrder = $line->stops()->where('stop_order', $attributes['stop_order'])->exists();
            if ($existingOrder) {
                throw ValidationException::withMessages([
                    'stop_order' => 'L’ordre '.$attributes['stop_order'].' est déjà utilisé sur cette ligne.',
                ]);
            }

            return $line->stops()->create([
                ...$attributes,
                'uuid' => Str::uuid()->toString(),
            ])->refresh();
        });
    }
}
