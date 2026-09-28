<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\PhysicalStop;

class ResolveStopsPayloadAction
{
    public function __construct(private FindOrCreatePhysicalStopAction $findOrCreatePhysicalStop) {}

    /**
     * Résout chaque arrêt : référence existante ou point carte à persister.
     *
     * @param  array<int, array<string, mixed>>  $stops
     * @return array<int, array{uuid?: string, name: string, city: string, country: string, stop_order: int, physical_stop_id: int, km_from_origin: float, latitude: float|null, longitude: float|null}>
     */
    public function execute(array $stops): array
    {
        return collect($stops)->map(function (array $stop): array {
            $resolved = [
                'uuid' => $stop['uuid'] ?? null,
                'name' => trim((string) $stop['name']),
                'city' => trim((string) $stop['city']),
                'country' => strtoupper(trim((string) $stop['country'])),
                'stop_order' => (int) $stop['stop_order'],
                'km_from_origin' => isset($stop['km_from_origin']) ? (float) $stop['km_from_origin'] : 0.0,
                'latitude' => isset($stop['latitude']) ? (float) $stop['latitude'] : null,
                'longitude' => isset($stop['longitude']) ? (float) $stop['longitude'] : null,
            ];

            if (! empty($stop['physical_stop_uuid'])) {
                $resolved['physical_stop_id'] = PhysicalStop::query()
                    ->where('uuid', $stop['physical_stop_uuid'])
                    ->firstOrFail()
                    ->id;
            } else {
                $physical = $this->findOrCreatePhysicalStop->execute([
                    'name' => $resolved['name'],
                    'city' => $resolved['city'],
                    'country' => $resolved['country'],
                    'latitude' => $resolved['latitude'],
                    'longitude' => $resolved['longitude'],
                ]);
                $resolved['physical_stop_id'] = $physical->id;
            }

            return $resolved;
        })->values()->all();
    }
}
