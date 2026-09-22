<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Tracking\Models\TrackingSession;
use Illuminate\Support\Facades\Cache;

/**
 * Cache de lecture rapide de la dernière position par session (ticket
 * MEZ-100 : "conserver la dernière position dans un cache de lecture
 * rapide"). La table `tracking_sessions` reste la source de vérité
 * (colonnes `last_*`, écrites dans la même transaction que l'insertion de
 * la position) — ce cache n'est qu'une optimisation de lecture devant elle,
 * jamais consultée pour une décision d'écriture (idempotence, ordre des
 * séquences).
 */
class TrackingPositionCache
{
    private function key(TrackingSession $session): string
    {
        return "tracking:last-position:{$session->uuid}";
    }

    /**
     * @return array<string, mixed>
     */
    public function remember(TrackingSession $session): array
    {
        return Cache::remember(
            $this->key($session),
            (int) config('tracking.cache_ttl_seconds', 600),
            fn (): array => $this->snapshot($session),
        );
    }

    public function put(TrackingSession $session): void
    {
        Cache::put(
            $this->key($session),
            $this->snapshot($session),
            (int) config('tracking.cache_ttl_seconds', 600),
        );
    }

    public function forget(TrackingSession $session): void
    {
        Cache::forget($this->key($session));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TrackingSession $session): array
    {
        return [
            'session_uuid' => $session->uuid,
            'status' => $session->status->value,
            'last_position_at' => $session->last_position_at?->toISOString(),
            'last_latitude' => $session->last_latitude,
            'last_longitude' => $session->last_longitude,
            'last_accuracy_meters' => $session->last_accuracy_meters,
            'last_speed_kmh' => $session->last_speed_kmh,
            'last_heading_degrees' => $session->last_heading_degrees,
            'last_quality' => $session->last_quality?->value,
            'last_status' => $session->last_status?->value,
            'last_sequence' => $session->last_sequence,
        ];
    }
}
