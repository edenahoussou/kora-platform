<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\PhysicalStop;
use Illuminate\Validation\ValidationException;

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
        return collect($stops)->map(function (array $stop, int $index): array {
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
                $physical = PhysicalStop::query()
                    ->where('uuid', $stop['physical_stop_uuid'])
                    ->firstOrFail();

                if ($this->normalizeCity($resolved['city']) !== $this->normalizeCity((string) $physical->city)) {
                    throw ValidationException::withMessages([
                        "stops.{$index}.physical_stop_uuid" => "L'arrêt « {$resolved['name']} » est déclaré à {$resolved['city']} mais le point « {$physical->name} » se situe à {$physical->city}. Supprimez cet arrêt et rajoutez-le en choisissant un point situé à {$resolved['city']}.",
                    ]);
                }

            } else {
                $physical = $this->findOrCreatePhysicalStop->execute([
                    'name' => $resolved['name'],
                    'city' => $this->canonicalCity($resolved['city']),
                    'country' => $resolved['country'],
                    'latitude' => $resolved['latitude'],
                    'longitude' => $resolved['longitude'],
                ], "stops.{$index}.name");
            }

            return [...$resolved, ...PhysicalStop::canonicalStopAttributes($physical), 'physical_stop_id' => $physical->id];
        })->values()->all();
    }

    /** Reuses the existing spelling of a city so « Bohicon » and « bohicon » never coexist. */
    private function canonicalCity(string $city): string
    {
        $normalized = $this->normalizeCity($city);

        return PhysicalStop::query()
            ->whereNotNull('city')
            ->distinct()
            ->pluck('city')
            ->first(fn (string $existing): bool => $this->normalizeCity($existing) === $normalized) ?? $city;
    }

    private function normalizeCity(string $city): string
    {
        return FindOrCreatePhysicalStopAction::normalize($city);
    }
}
