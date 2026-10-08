<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Actions\CorrectManifestPassengerAction;
use App\Modules\Departures\Http\Requests\CorrectManifestPassengerRequest;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

use function Illuminate\Support\enum_value;

/**
 * @group OPS Mobile — Manifest
 *
 * Correction par le convoyeur des informations d'un passager.
 */
class OpsPassengerCorrectionController extends Controller
{
    /**
     * Corrige les informations d'un passager du départ.
     *
     * Possible avant comme après l'embarquement. Réservé au personnel
     * affecté au départ avec la permission d'embarquement. Chaque
     * correction effective est journalisée (auteur, motif, anciennes et
     * nouvelles valeurs, données sensibles masquées).
     *
     * @authenticated
     *
     * @header X-Terminal-Serial Numéro de série du terminal. Example: P501-000123
     *
     * @urlParam departure string required UUID public du départ. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     * @urlParam ticket string required UUID public du billet du passager. Example: 3a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e41
     *
     * @response 200 scenario="Passager corrigé" {
     *   "data": { "ticketUuid": "3a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e41", "name": "Awa Traoré", "firstName": "Awa", "lastName": "Traoré", "phone": "+22990000000", "email": null }
     * }
     * @response 422 scenario="Billet hors périmètre de ce départ" {
     *   "error": { "code": "ticket_not_for_departure", "message": "Ce billet ne correspond pas à ce départ." }
     * }
     */
    public function update(
        CorrectManifestPassengerRequest $request,
        Departure $departure,
        Ticket $ticket,
        CorrectManifestPassengerAction $correct,
    ): JsonResponse {
        Gate::authorize('scanBoarding', $departure);

        $belongsToDeparture = ReservationSegment::query()
            ->where('reservation_passenger_id', $ticket->reservation_passenger_id)
            ->where('departure_id', $departure->id)
            ->exists();

        // $belongsToDeparture = $ticket->reservation_passenger_id !== null
        //     && ReservationSegment::query()
        //         ->where('reservation_passenger_id', $ticket->reservation_passenger_id)
        //         ->where('departure_id', $departure->id)
        //         ->exists();

        if (! $belongsToDeparture) {
            return response()->json([
                'error' => [
                    'code' => 'ticket_not_for_departure',
                    'message' => 'Ce billet ne correspond pas à ce départ.',
                ],
            ], 422);
        }

        /** @var TerminalP501|null $terminal */
        $terminal = $request->attributes->get('terminal');

        $passenger = $correct->execute(
            $departure,
            $ticket,
            $request->user(),
            $request->changes(),
            $request->reason(),
            $terminal,
        );

        return response()->json(['data' => [
            'ticketUuid' => $ticket->uuid,
            'name' => trim($passenger->first_name.' '.$passenger->last_name),
            'firstName' => $passenger->first_name,
            'lastName' => $passenger->last_name,
            'phone' => $passenger->phone,
            'email' => $passenger->email,
            'document' => $passenger->document_type === null && blank($passenger->document_number) ? null : [
                'type' => $passenger->document_type === null ? null : enum_value($passenger->document_type),
                'number' => $passenger->document_number,
            ],
            'nationality' => $passenger->nationality,
            'birthDate' => $passenger->birth_date?->toDateString(),
            'yellowFeverVaccinated' => $passenger->yellow_fever_vaccinated,
        ]]);
    }
}
