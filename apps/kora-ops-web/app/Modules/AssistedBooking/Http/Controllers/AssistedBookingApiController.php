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
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssistedBookingApiController extends Controller
{
    public function store(
        StoreAssistedBookingRequest $request,
        Reservation $reservation,
        AssistedBookingWorkflow $workflow,
    ): JsonResponse {
        $data = $request->validated();
        $agency = Agency::query()->where('uuid', $data['agency_uuid'])->firstOrFail();
        $assistedBooking = $workflow->request(
            reservation: $reservation,
            actor: $request->user(),
            agency: $agency,
            channel: AssistedBookingChannel::from($data['channel']),
            contactName: $data['contact_name'],
            contactPhone: $data['contact_phone'],
            idempotencyKey: $data['idempotency_key'],
        );

        return response()->json(
            ['data' => new AssistedBookingResource($assistedBooking->loadMissing(['reservation', 'preferredAgency']))],
            $assistedBooking->wasRecentlyCreated ? 201 : 200,
        );
    }

    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('requestAssistance', $reservation);
        $assistedBooking = AssistedBooking::query()
            ->where('reservation_id', $reservation->getKey())
            ->with(['reservation', 'preferredAgency'])
            ->firstOrFail();

        return response()->json(['data' => new AssistedBookingResource($assistedBooking)]);
    }

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
