<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\PhysicalStop;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FindOrCreatePhysicalStopAction
{
    private const PROXIMITY_RADIUS_KM = 0.3;

    /**
     * @param  array{name: string, city: string, country: string, latitude?: float|null, longitude?: float|null}  $attributes
     */
    public function execute(array $attributes, string $errorKey = 'name'): PhysicalStop
    {
        $existing = $this->findNearby($attributes);

        if ($existing !== null) {
            if (self::normalize($existing->name) === self::normalize($attributes['name'])
                && self::normalize($existing->city) === self::normalize($attributes['city'])) {
                return $existing;
            }

            throw ValidationException::withMessages([
                $errorKey => "Le point « {$existing->name} » ({$existing->city}) existe déjà à moins de 300 m. Sélectionnez-le dans la liste des points existants ou éloignez le marqueur.",
            ]);
        }

        return PhysicalStop::query()->create([
            'uuid' => Str::uuid()->toString(),
            'code' => $this->generateCode(),
            'name' => $attributes['name'],
            'city' => $attributes['city'],
            'country' => strtoupper($attributes['country']),
            'latitude' => $attributes['latitude'] ?? null,
            'longitude' => $attributes['longitude'] ?? null,
            'status' => 'active',
        ]);
    }

    /**
     * Réutilise une gare existante à moins de ~300 m dans le même pays.
     *
     * @param  array{name?: string, city?: string, country?: string, latitude?: float|string|null, longitude?: float|string|null}  $attributes
     */
    private function findNearby(array $attributes): ?PhysicalStop
    {
        $latitude = isset($attributes['latitude']) ? (float) $attributes['latitude'] : null;
        $longitude = isset($attributes['longitude']) ? (float) $attributes['longitude'] : null;

        if ($latitude === null || $longitude === null) {
            return null;
        }

        $latDelta = self::PROXIMITY_RADIUS_KM / 111.0;
        $lngDelta = self::PROXIMITY_RADIUS_KM / (111.0 * max(0.2, cos(deg2rad($latitude))));

        return PhysicalStop::query()
            ->where('country', strtoupper((string) ($attributes['country'] ?? '')))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
            ->get()
            ->first(fn (PhysicalStop $stop): bool => $this->haversineKm(
                $latitude,
                $longitude,
                (float) $stop->latitude,
                (float) $stop->longitude,
            ) <= self::PROXIMITY_RADIUS_KM);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $toRad = fn (float $deg): float => $deg * M_PI / 180;
        $dLat = $toRad($lat2 - $lat1);
        $dLng = $toRad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public static function normalize(?string $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->replaceMatches('/[\s\-\']+/u', ' ')->trim()->value();
    }

    private function generateCode(): string
    {
        do {
            $code = 'PS-'.strtoupper(Str::random(6));
        } while (PhysicalStop::query()->where('code', $code)->exists());

        return $code;
    }
}
