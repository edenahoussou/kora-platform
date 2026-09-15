<?php

namespace App\Modules\Boarding\Actions;

use App\Models\User;
use App\Modules\Boarding\Data\BoardingEventStatus;
use App\Modules\Boarding\Models\BoardingEvent;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Annulation exceptionnelle d'un embarquement (erreur de scan, décision
 * opérationnelle). L'événement d'embarquement d'origine n'est jamais
 * modifié ni supprimé — seule une entrée activitylog immuable journalise
 * la correction, avec l'auteur et le motif obligatoire ; le segment
 * repasse à "ticketed" et redevient scannable.
 */
class ReverseBoardingEventAction
{
    public function execute(BoardingEvent $boardingEvent, User $actor, string $reason): BoardingEvent
    {
        if ($boardingEvent->status !== BoardingEventStatus::Accepted) {
            throw new RuntimeException('Seul un embarquement accepté peut être annulé.');
        }

        DB::transaction(function () use ($boardingEvent, $actor, $reason): void {
            // Le billet ne porte pas directement la jambe : on la
            // retrouve via (passager du billet, départ de l'événement).
            $segment = ReservationSegment::query()
                ->where('reservation_passenger_id', $boardingEvent->ticket->reservation_passenger_id)
                ->where('departure_id', $boardingEvent->departure_id)
                ->lockForUpdate()
                ->first();

            if ($segment !== null && $segment->status === ReservationSegmentStatus::Boarded) {
                $segment->update(['status' => ReservationSegmentStatus::Ticketed]);
            }

            activity()
                ->performedOn($boardingEvent)
                ->causedBy($actor)
                ->withProperties([
                    'reason' => $reason,
                    'ticket_uuid' => $boardingEvent->ticket->uuid,
                    'departure_uuid' => $boardingEvent->departure->uuid,
                    'terminal_uuid' => $boardingEvent->terminal?->uuid,
                ])
                ->log('boarding_event_reversed');
        });

        return $boardingEvent;
    }
}
