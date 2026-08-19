<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\Line;
use Illuminate\Support\Facades\DB;

class UpdateLineAction
{
    /**
     * @param  array{code?: string, name?: string, countries_crossed?: array<string>|null, distance_km?: int|null, estimated_duration_minutes?: int|null, status?: string}  $attributes
     */
    public function execute(Line $line, array $attributes): Line
    {
        return DB::transaction(function () use ($line, $attributes): Line {
            $line->update($attributes);

            return $line->refresh()->load('stops');
        });
    }
}
