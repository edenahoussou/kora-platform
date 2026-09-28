<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SyncLineStopsAction
{
    /**
     * Réconcilie les arrêts d'une ligne avec le parcours envoyé par le studio.
     *
     * @param  array<int, array{uuid?: string|null, name: string, city: string, country: string, stop_order: int, physical_stop_id: int, km_from_origin: float, latitude: float|null, longitude: float|null}>  $stops
     */
    public function execute(Line $line, array $stops): void
    {
        DB::transaction(function () use ($line, $stops): void {
            Line::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();

            if ($line->departures()->exists()) {
                throw ValidationException::withMessages([
                    'stops' => 'Ce parcours est utilisé par des départs publiés. Créez une nouvelle ligne pour changer les segments.',
                ]);
            }

            $existingStops = $line->stops()->get()->keyBy('uuid');
            $incomingUuids = collect($stops)->pluck('uuid')->filter()->all();

            // Décale les ordres existants : UNIQUE (line_id, stop_order) interdit les échanges directs
            foreach ($existingStops as $existingStop) {
                $existingStop->update(['stop_order' => $existingStop->stop_order + 1000]);
            }

            foreach ($stops as $index => $data) {
                $attributes = [
                    'name' => $data['name'],
                    'city' => $data['city'],
                    'country' => $data['country'],
                    'stop_order' => $index + 1,
                    'physical_stop_id' => $data['physical_stop_id'],
                    'km_from_origin' => $data['km_from_origin'],
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                ];

                $existing = ! empty($data['uuid']) ? $existingStops->get($data['uuid']) : null;

                if ($existing instanceof Stop) {
                    $existing->update($attributes);
                } else {
                    $line->stops()->create([...$attributes, 'uuid' => Str::uuid()->toString()]);
                }
            }

            $existingStops
                ->reject(fn (Stop $stop): bool => in_array($stop->uuid, $incomingUuids, true))
                ->each(function (Stop $stop): void {
                    if ($stop->originFares()->exists() || $stop->destinationFares()->exists()) {
                        throw ValidationException::withMessages([
                            'stops' => "L'arrêt « {$stop->name} » ne peut pas être retiré : des tarifs y sont rattachés.",
                        ]);
                    }

                    $stop->delete();
                });
        });
    }
}
