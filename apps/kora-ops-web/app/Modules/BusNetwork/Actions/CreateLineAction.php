<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\Line;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateLineAction
{
    /**
     * @param  array{code: string, name: string, countries_crossed?: array<string>|null, distance_km?: int|null, estimated_duration_minutes?: int|null, status: string}  $attributes
     * @param  array<int, array{name: string, city: string, country: string, stop_order: int, km_from_origin?: float, latitude?: float|null, longitude?: float|null}>  $stops
     */
    public function execute(array $attributes, array $stops = []): Line
    {
        return DB::transaction(function () use ($attributes, $stops): Line {
            $line = Line::query()->create([
                ...$attributes,
                'uuid' => Str::uuid()->toString(),
            ]);

            foreach ($stops as $stop) {
                $line->stops()->create([
                    'uuid' => Str::uuid()->toString(),
                    'name' => $stop['name'],
                    'city' => $stop['city'],
                    'country' => $stop['country'],
                    'stop_order' => $stop['stop_order'],
                    'km_from_origin' => $stop['km_from_origin'] ?? 0,
                    'latitude' => $stop['latitude'] ?? null,
                    'longitude' => $stop['longitude'] ?? null,
                ]);
            }

            return $line->refresh()->load('stops');
        });
    }
}
