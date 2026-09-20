<?php

namespace App\Modules\AssistedBooking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AssistedBooking\Data\AssistedBookingChannel;
use App\Modules\AssistedBooking\Http\Requests\ResumeAssistedBookingOnlineRequest;
use App\Modules\AssistedBooking\Http\Requests\StoreAssistedBookingRequest;
use App\Modules\AssistedBooking\Http\Resources\AssistedBookingResource;
use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\AssistedBooking\Services\AssistedBookingWorkflow;
use App\Modules\Booking\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Assisted Bookings
 *
 * Assisted booking lifecycle for the mobile app: contact central support,
 * inspect the dossier, or resume online payment.
 */
class AssistedBookingApiController extends Controller
{
    /**
     * Send a reservation to central support for assisted completion.
     *
     * Transforms the existing reservation (no duplicate is created): seats
     * and company credit are released, segments become `requested`, and the
     * dossier enters the network queue as `pending`. Replaying the same
     * `idempotency_key` returns the existing dossier.
     *
     * @bodyParam channel string required Initial contact channel: call or whatsapp. Example: whatsapp
     * @bodyParam contact_name string required Contact first name shown to the agency. Example: Afi
     * @bodyParam contact_phone string required Contact phone in international format. Example: +2290197000000
     * @bodyParam idempotency_key string required Client-generated idempotency key. Example: 1f3d5b7a-9c2e-4f6a-8b0d-2e4f6a8c0d2f
     *
     * @response 201 scenario="Assistance requested" {
     *   "data": {
     *     "uuid": "7a1e9c3b-4d5f-4a2b-8c1d-3e5f7a9b1c2d",
     *     "status": "pending",
     *     "channel": "whatsapp",
     *     "reservation": {
     *       "uuid": "5b0d2f4a-6c8e-4a1b-9d3f-1a3c5e7b9d2f",
     *       "reference": "KBR-7X2K9P4Q",
     *       "status": "assistance_pending"
     *     },
     *     "handling_agency": null,
     *     "assigned_agent": null,
     *     "contact": {"name": "Afi", "phone": "+2290197000000"},
     *     "channels": {
     *       "call_phone": "+229010000001",
     *       "whatsapp_phone": "+229010000002",
     *       "call_url": "tel:+229010000001",
     *       "whatsapp_url": "https://wa.me/229010000002?text=Bonjour%20KORA%20BUS"
     *     },
     *     "whatsapp_message": "Bonjour KORA BUS ...",
     *     "amount": {"original": 8500, "current": 8500, "currency": "XOF"},
     *     "valid_until": "2026-09-20T11:30:00+00:00",
     *     "actions": {"resume_online": true}
     *   }
     * }
     * @response 200 scenario="Idempotent replay" {
     *   "data": {
     *     "uuid": "7a1e9c3b-4d5f-4a2b-8c1d-3e5f7a9b1c2d",
     *     "status": "pending"
     *   }
     * }
     * @response 403 scenario="Not the reservation owner" {
     *   "message": "This action is unauthorized."
     * }
     * @response 422 scenario="Reservation not eligible" {
     *   "message": "The given data was invalid.",
     *   "errors": {"reservation": ["Cette réservation ne peut pas être envoyée en assistance."]}
     * }
     * @response 503 scenario="Support channel unavailable" {
     *   "error": {"code": "assisted_booking_support_unavailable", "channel": "whatsapp"}
     * }
     */
    public function store(
        StoreAssistedBookingRequest $request,
        Reservation $reservation,
        AssistedBookingWorkflow $workflow,
    ): JsonResponse {
        $data = $request->validated();
        $assistedBooking = $workflow->request(
            reservation: $reservation,
            actor: $request->user(),
            channel: AssistedBookingChannel::from($data['channel']),
            contactName: $data['contact_name'],
            contactPhone: $data['contact_phone'],
            idempotencyKey: $data['idempotency_key'],
        );

        return response()->json(
            ['data' => new AssistedBookingResource($assistedBooking->loadMissing(['reservation', 'handlingAgency', 'assignedUser']))],
            $assistedBooking->wasRecentlyCreated ? 201 : 200,
        );
    }

    /**
     * Show the assisted booking dossier for a reservation.
     *
     * Returns the current status, validity window, central support channels,
     * the initial snapshot and the actions still allowed.
     *
     * @response 200 scenario="Dossier detail" {
     *   "data": {
     *     "uuid": "7a1e9c3b-4d5f-4a2b-8c1d-3e5f7a9b1c2d",
     *     "status": "pending",
     *     "channel": "whatsapp",
     *     "reservation": {
     *       "uuid": "5b0d2f4a-6c8e-4a1b-9d3f-1a3c5e7b9d2f",
     *       "reference": "KBR-7X2K9P4Q",
     *       "status": "assistance_pending"
     *     },
     *     "handling_agency": null,
     *     "assigned_agent": null,
     *     "contact": {"name": "Afi", "phone": "+2290197000000"},
     *     "channels": {
     *       "call_phone": "+229010000001",
     *       "whatsapp_phone": "+229010000002",
     *       "call_url": "tel:+229010000001",
     *       "whatsapp_url": "https://wa.me/229010000002?text=Bonjour%20KORA%20BUS"
     *     },
     *     "whatsapp_message": "Bonjour KORA BUS ...",
     *     "amount": {"original": 8500, "current": 8500, "currency": "XOF"},
     *     "valid_until": "2026-09-20T11:30:00+00:00",
     *     "actions": {"resume_online": true}
     *   }
     * }
     * @response 403 scenario="Not the reservation owner" {
     *   "message": "This action is unauthorized."
     * }
     * @response 404 scenario="No assisted booking" {
     *   "message": "No query results for model [App\\Modules\\AssistedBooking\\Models\\AssistedBooking]."
     * }
     */
    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('requestAssistance', $reservation);
        $assistedBooking = AssistedBooking::query()
            ->where('reservation_id', $reservation->getKey())
            ->with(['reservation', 'handlingAgency', 'assignedUser'])
            ->firstOrFail();

        return response()->json(['data' => new AssistedBookingResource($assistedBooking)]);
    }

    /**
     * Resume online payment on the same reservation.
     *
     * Arbitrates against a concurrent agency claim: only valid while the
     * dossier is still `pending`. The quote is recalculated and the seats
     * are re-acquired atomically on the same itinerary — the first
     * successful operation wins and the loser gets a 409 conflict.
     *
     * @bodyParam idempotency_key string required Client-generated idempotency key. Example: 1f3d5b7a-9c2e-4f6a-8b0d-2e4f6a8c0d2f
     * @bodyParam passengers array required Per-passenger seat assignments.
     * @bodyParam passengers[].passenger_index integer required Passenger index. Example: 0
     * @bodyParam passengers[].seat_assignments array required Seats requested per leg.
     * @bodyParam passengers[].seat_assignments[].leg_order integer required Leg order. Example: 1
     * @bodyParam passengers[].seat_assignments[].seat_uuid string required UUID of the chosen seat. Example: 8d3f6a2c-1e4b-4c5d-9a2f-4b6d8e0f2a4c
     *
     * @response 200 scenario="Resumed online" {
     *   "data": {
     *     "uuid": "5b0d2f4a-6c8e-4a1b-9d3f-1a3c5e7b9d2f",
     *     "reference": "KBR-7X2K9P4Q",
     *     "status": "held",
     *     "channel": "mobile",
     *     "amount": 8500,
     *     "currency": "XOF",
     *     "expires_at": "2026-09-20T11:45:00+00:00",
     *     "seats": [
     *       {
     *         "segment_uuid": "2c4e6a8b-0d2f-4a6c-8e0b-2d4f6a8c0e2b",
     *         "passenger_index": 0,
     *         "leg_order": 1,
     *         "seat_uuid": "8d3f6a2c-1e4b-4c5d-9a2f-4b6d8e0f2a4c",
     *         "seat_code": "2A"
     *       }
     *     ]
     *   }
     * }
     * @response 409 scenario="Already claimed by an agency" {
     *   "message": "Le dossier est déjà pris en charge par une agence."
     * }
     * @response 409 scenario="Seat no longer available" {
     *   "message": "Le siège 2A n'est plus disponible sur ce segment.",
     *   "conflicting_seat_uuid": "8d3f6a2c-1e4b-4c5d-9a2f-4b6d8e0f2a4c",
     *   "seat_code": "2A"
     * }
     * @response 403 scenario="Not the reservation owner" {
     *   "message": "This action is unauthorized."
     * }
     */
    public function resumeOnline(
        ResumeAssistedBookingOnlineRequest $request,
        Reservation $reservation,
        AssistedBookingWorkflow $workflow,
    ): JsonResponse {
        $assistedBooking = AssistedBooking::query()->where('reservation_id', $reservation->getKey())->firstOrFail();
        $reservation = $workflow->resumeOnline(
            assistedBooking: $assistedBooking,
            actor: $request->user(),
            seatAssignments: $request->validated('passengers'),
            idempotencyKey: $request->validated('idempotency_key'),
        );

        return response()->json(['data' => [
            'uuid' => $reservation->uuid,
            'reference' => $reservation->reference,
            'status' => $reservation->status->value,
            'channel' => $reservation->channel->value,
            'amount' => $reservation->total_amount,
            'currency' => $reservation->currency,
            'expires_at' => $reservation->expires_at?->toIso8601String(),
            'seats' => $reservation->segments->map(fn ($segment): array => [
                'segment_uuid' => $segment->uuid,
                'passenger_index' => $segment->passenger_index,
                'leg_order' => $segment->leg_order,
                'seat_uuid' => $segment->seat->uuid,
                'seat_code' => $segment->seat->code,
            ])->values(),
        ]]);
    }
}
