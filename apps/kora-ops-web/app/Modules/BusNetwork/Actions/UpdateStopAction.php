<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateStopAction
{
    /**
     * @param  array{name?: string, city?: string, country?: string, stop_order?: int, km_from_origin?: float, latitude?: float|null, longitude?: float|null, status?: string}  $attributes
     */
    public function execute(Stop $stop, array $attributes): Stop
    {
        return DB::transaction(function () use ($stop, $attributes): Stop {
            Line::query()->whereKey($stop->line->id)->lockForUpdate()->firstOrFail();
            $structuralChange = collect($attributes)->contains(fn ($value, $key): bool => in_array($key, ['line_id', 'stop_order', 'status'], true) && $value != $stop->getRawOriginal($key));
            if ($stop->line->departures()->exists() && $structuralChange) {
                throw ValidationException::withMessages(['stop_order' => 'Ce parcours est utilisé par des départs publiés. Créez une nouvelle ligne pour changer les segments.']);
            }
            if (isset($attributes['stop_order']) && $attributes['stop_order'] !== $stop->getRawOriginal('stop_order')) {
                $conflict = $stop->line->stops()
                    ->where('stop_order', $attributes['stop_order'])
                    ->where('id', '!=', $stop->id)
                    ->exists();
                if ($conflict) {
                    throw ValidationException::withMessages([
                        'stop_order' => 'L’ordre '.$attributes['stop_order'].' est déjà utilisé sur cette ligne.',
                    ]);
                }
            }

            $stop->update($attributes);

            return $stop->refresh();
        });
    }
}
