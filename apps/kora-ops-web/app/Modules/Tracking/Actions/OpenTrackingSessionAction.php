<?php

namespace App\Modules\Tracking\Actions;

use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Tracking\Data\TrackingSessionStatus;
use App\Modules\Tracking\Models\TrackingSession;

/**
 * Ouvre la session de suivi d'un départ au départ effectif (ticket MEZ-100).
 * Idempotent : un départ qui a déjà une session ouverte la réutilise (pas de
 * doublon) — utile à la fois pour l'observateur (déclenché une fois par
 * transition de statut, en principe) et pour l'ouverture défensive depuis
 * l'ingestion de positions (voir IngestTrackingPositionsBatchAction).
 */
class OpenTrackingSessionAction
{
    /**
     * Retourne null si le départ n'a pas de convoyeur affecté : rien à
     * suivre. `bus_id` n'est volontairement pas revérifié ici — la colonne
     * est NOT NULL en base (`departures.bus_id`), un départ existe toujours
     * avec un bus. Le départ progresse quand même (voir
     * OpsDepartureProgressController) ; simplement aucune session de suivi
     * n'est ouverte tant que le convoyeur n'est pas affecté.
     */
    public function execute(Departure $departure, TerminalP501 $terminal): ?TrackingSession
    {
        $existing = TrackingSession::query()
            ->where('departure_id', $departure->id)
            ->open()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($departure->conductor_id === null) {
            return null;
        }

        return TrackingSession::query()->create([
            'departure_id' => $departure->id,
            'bus_id' => $departure->bus_id,
            'terminal_p501_id' => $terminal->id,
            'conductor_id' => $departure->conductor_id,
            'status' => TrackingSessionStatus::Open,
            'opened_at' => now(),
        ]);
    }
}
