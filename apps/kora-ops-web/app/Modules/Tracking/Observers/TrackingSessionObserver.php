<?php

namespace App\Modules\Tracking\Observers;

use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Tracking\Actions\CloseTrackingSessionAction;
use App\Modules\Tracking\Actions\OpenTrackingSessionAction;
use App\Modules\Tracking\Data\TrackingSessionCloseReason;
use App\Modules\Tracking\Data\TrackingSessionStatus;
use App\Modules\Tracking\Models\TrackingSession;
use Throwable;

/**
 * Ouvre la session de suivi au départ effectif, la clôt à l'arrivée ou à
 * l'annulation (ticket MEZ-100). Se branche sur le même signal que
 * DepartureOpsNotificationObserver (transition de `status`) plutôt que sur
 * `departed_at`/`arrived_at` directement : c'est le statut qui fait foi
 * pour OPS Web comme pour OPS Mobile (voir OpsDepartureProgressController).
 *
 * Contrairement à DepartureOpsNotificationObserver, l'ouverture/clôture est
 * exécutée immédiatement (pas de DB::afterCommit) : il ne s'agit que
 * d'écritures dans la même connexion que le départ lui-même (aucun effet de
 * bord externe irréversible à cette étape — la diffusion temps réel se fait
 * séparément, à l'ingestion d'une position, voir IngestTrackingPositionsBatchAction).
 * Ces écritures suivent donc naturellement le sort de la transaction
 * englobante, sans avoir besoin d'attendre son commit.
 *
 * N'échoue jamais l'écriture du départ qui déclenche l'observateur : une
 * ouverture/clôture de session qui échoue est journalisée et n'empêche pas
 * la progression du départ lui-même.
 */
class TrackingSessionObserver
{
    public function __construct(
        private OpenTrackingSessionAction $open,
        private CloseTrackingSessionAction $close,
    ) {}

    public function updated(Departure $departure): void
    {
        if (! $departure->wasChanged('status')) {
            return;
        }

        $status = $departure->status;

        try {
            if ($status === DepartureStatus::Departed) {
                $this->openSession($departure);
            } elseif ($status === DepartureStatus::Arrived) {
                $this->closeSession($departure, TrackingSessionCloseReason::Arrived);
            } elseif ($status === DepartureStatus::Cancelled) {
                $this->closeSession($departure, TrackingSessionCloseReason::Cancelled);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function openSession(Departure $departure): void
    {
        if ($departure->conductor_id === null) {
            return;
        }

        $terminal = TerminalP501::query()
            ->where('assigned_user_id', $departure->conductor_id)
            ->where('status', TerminalP501Status::Active)
            ->first();

        // Aucun terminal actif affecté à ce convoyeur pour l'instant : rien
        // à ouvrir. L'ingestion de positions ouvrira la session elle-même,
        // dès que le convoyeur aura un terminal actif (voir
        // IngestTrackingPositionsBatchAction / OpsTrackingController).
        if ($terminal === null) {
            return;
        }

        $this->open->execute($departure, $terminal);
    }

    private function closeSession(Departure $departure, TrackingSessionCloseReason $reason): void
    {
        $session = TrackingSession::query()
            ->where('departure_id', $departure->id)
            ->where('status', TrackingSessionStatus::Open)
            ->first();

        if ($session !== null) {
            $this->close->execute($session, $reason);
        }
    }
}
