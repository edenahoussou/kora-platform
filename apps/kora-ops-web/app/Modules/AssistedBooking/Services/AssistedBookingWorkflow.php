<?php

namespace App\Modules\AssistedBooking\Services;

use App\Models\User;
use App\Modules\AssistedBooking\Data\AssistedBookingChannel;
use App\Modules\AssistedBooking\Data\AssistedBookingStatus;
use App\Modules\AssistedBooking\Exceptions\AssistedBookingConflictException;
use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\Booking\Actions\ApplyItinerarySeatHoldAction;
use App\Modules\Booking\Actions\CalculateItineraryQuoteAction;
use App\Modules\Booking\Actions\ItineraryLegQuoteRequest;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use App\Modules\Departures\Actions\ResolveDepartureSellabilityAction;
use App\Modules\Departures\Data\SellabilityReason;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\SeatInventory\Actions\BuildSeatAvailabilityAction;
use App\Modules\SeatInventory\Data\SeatAvailabilityStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\enum_value;

class AssistedBookingWorkflow
{
    public function __construct(
        private ResolveDepartureSellabilityAction $resolveDepartureSellability,
        private CompanyCreditService $companyCreditService,
        private CalculateItineraryQuoteAction $calculateItineraryQuote,
        private ApplyItinerarySeatHoldAction $applyItinerarySeatHold,
        private BuildSeatAvailabilityAction $buildSeatAvailability,
        private AssistedBookingSupportChannels $supportChannels,
    ) {}

    public function claim(AssistedBooking $assistedBooking, User $actor): AssistedBooking
    {
        $result = DB::transaction(function () use ($assistedBooking, $actor): AssistedBooking {
            $reservation = Reservation::query()->whereKey($assistedBooking->reservation_id)->lockForUpdate()->firstOrFail();
            $lockedAssistance = AssistedBooking::query()->whereKey($assistedBooking->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAssistance->status !== AssistedBookingStatus::Pending) {
                throw new AssistedBookingConflictException('Ce dossier a déjà été pris en charge ou clôturé.');
            }
            if ($actor->agency_id === null) {
                throw new AssistedBookingConflictException('Un agent rattaché à une agence est requis pour prendre ce dossier.');
            }
            if ($lockedAssistance->preferred_agency_id !== null
                && $actor->agency_id !== $lockedAssistance->preferred_agency_id) {
                throw new AssistedBookingConflictException('Ce dossier est placé dans la file d’une autre agence.');
            }
            if (! $this->ensureNotExpired($lockedAssistance, $reservation, $actor)) {
                return $lockedAssistance;
            }
            $quote = $this->recalculateQuote($reservation);

            $previousAmount = (int) $reservation->total_amount;
            $reservation->update([
                'itinerary_quote_id' => $quote->id,
                'promotion_id' => $quote->promotion_id,
                'promotion_discount_amount' => (int) $quote->promotion_discount_amount,
                'total_amount' => $quote->total_amount,
                'currency' => $quote->currency,
                'total_snapshot' => $quote->snapshot,
            ]);
            $lockedAssistance->update([
                'preferred_agency_id' => $actor->agency_id,
                'status' => AssistedBookingStatus::InProgress,
                'assigned_user_id' => $actor->id,
                'current_total_amount' => $quote->total_amount,
                'claimed_at' => now(),
                'reason_code' => null,
                'reason_note' => null,
            ]);
            $this->audit($lockedAssistance, $actor, 'claimed', [
                'previous_amount' => $previousAmount,
                'current_amount' => $quote->total_amount,
            ]);

            return $lockedAssistance->load(['reservation', 'handlingAgency', 'assignedUser']);
        });

        if (in_array($result->status, [AssistedBookingStatus::Expired, AssistedBookingStatus::Unavailable], true)) {
            throw new AssistedBookingConflictException('Cet itinéraire n’est plus disponible à la vente.');
        }

        return $result;
    }

    /**
     * @param  list<array{passenger_index: int, seat_assignments: list<array{leg_order: int, seat_uuid: string}>}>  $seatAssignments
     */
    public function resumeOnline(
        AssistedBooking $assistedBooking,
        User $actor,
        array $seatAssignments,
        string $idempotencyKey,
    ): Reservation {
        $result = DB::transaction(function () use ($assistedBooking, $actor, $seatAssignments, $idempotencyKey): Reservation|AssistedBooking {
            $reservation = Reservation::query()->whereKey($assistedBooking->reservation_id)->lockForUpdate()->firstOrFail();
            $lockedAssistance = AssistedBooking::query()->whereKey($assistedBooking->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAssistance->status === AssistedBookingStatus::Cancelled
                && $lockedAssistance->resume_idempotency_key === $idempotencyKey) {
                return $reservation->load(['itineraryQuote', 'segments.seat', 'passengers']);
            }
            if ($lockedAssistance->status !== AssistedBookingStatus::Pending) {
                throw new AssistedBookingConflictException('Un agent a déjà pris en charge ce dossier ou celui-ci est clôturé.');
            }
            if ($lockedAssistance->preferred_agency_id !== null) {
                throw new AssistedBookingConflictException('Ce dossier est déjà placé dans la file d’une agence.');
            }
            if (! $this->ensureNotExpired($lockedAssistance, $reservation, $actor)) {
                return $lockedAssistance;
            }
            $quote = $this->recalculateQuote($reservation);
            $reservation = $this->applyItinerarySeatHold->handle(
                $reservation,
                $quote,
                $seatAssignments,
                ReservationChannel::Mobile,
                replaceRequestedSegments: true,
            );
            if ($reservation->company_account_id !== null) {
                $this->companyCreditService->reserve($reservation, $reservation->companyAccount()->firstOrFail());
            }

            $lockedAssistance->update([
                'status' => AssistedBookingStatus::Cancelled,
                'current_total_amount' => $quote->total_amount,
                'resume_idempotency_key' => $idempotencyKey,
                'reason_code' => 'resumed_online',
                'cancelled_at' => now(),
            ]);
            $this->audit($lockedAssistance, $actor, 'resumed_online', [
                'current_amount' => $quote->total_amount,
                'seat_codes' => $reservation->segments->pluck('seat.code')->filter()->values()->all(),
            ]);

            return $reservation;
        });

        if ($result instanceof AssistedBooking) {
            throw new AssistedBookingConflictException('Cet itinéraire n’est plus disponible à la vente.');
        }

        return $result;
    }

    public function release(AssistedBooking $assistedBooking, User $actor, string $reason): AssistedBooking
    {
        return DB::transaction(function () use ($assistedBooking, $actor, $reason): AssistedBooking {
            $reservation = Reservation::query()->whereKey($assistedBooking->reservation_id)->lockForUpdate()->firstOrFail();
            $lockedAssistance = AssistedBooking::query()->whereKey($assistedBooking->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedAssistance->status !== AssistedBookingStatus::InProgress) {
                throw new AssistedBookingConflictException('Seul un dossier en cours peut être libéré.');
            }
            if ($reservation->status === ReservationStatus::Held && $reservation->channel === ReservationChannel::Counter) {
                $reservation->segments()->update([
                    'status' => ReservationSegmentStatus::Requested,
                    'released_at' => now(),
                ]);
                $this->companyCreditService->release($reservation);
                $reservation->update(['status' => ReservationStatus::AssistancePending]);
            }
            $previousAssignee = $lockedAssistance->assignedUser()->value('uuid');
            $lockedAssistance->update([
                'status' => AssistedBookingStatus::Pending,
                'preferred_agency_id' => null,
                'assigned_user_id' => null,
                'claimed_at' => null,
                'released_at' => now(),
                'reason_code' => 'released',
                'reason_note' => trim($reason),
            ]);
            $this->audit($lockedAssistance, $actor, 'released', [
                'previous_assignee_uuid' => $previousAssignee,
                'reason' => trim($reason),
            ]);

            return $lockedAssistance->load(['reservation', 'handlingAgency', 'assignedUser']);
        });
    }

    public function reassign(
        AssistedBooking $assistedBooking,
        User $actor,
        Agency $agency,
        string $reason,
    ): AssistedBooking {
        return DB::transaction(function () use ($assistedBooking, $actor, $agency, $reason): AssistedBooking {
            Reservation::query()->whereKey($assistedBooking->reservation_id)->lockForUpdate()->firstOrFail();
            $lockedAssistance = AssistedBooking::query()->whereKey($assistedBooking->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedAssistance->status->isTerminal()) {
                throw new AssistedBookingConflictException('Un dossier clôturé ne peut pas être réaffecté.');
            }
            if (enum_value($agency->status) !== AgencyStatus::Active->value) {
                throw ValidationException::withMessages(['agency_uuid' => 'Cette agence n’est pas éligible à l’assistance.']);
            }

            $previousAgencyUuid = $lockedAssistance->handlingAgency()->value('uuid');
            $previousAssigneeUuid = $lockedAssistance->assignedUser()->value('uuid');
            $lockedAssistance->update([
                'preferred_agency_id' => $agency->id,
                'assigned_user_id' => null,
                'status' => AssistedBookingStatus::Pending,
                'claimed_at' => null,
                'released_at' => now(),
                'reason_code' => 'reassigned',
                'reason_note' => trim($reason),
            ]);
            $this->audit($lockedAssistance, $actor, 'reassigned', [
                'previous_agency_uuid' => $previousAgencyUuid,
                'previous_assignee_uuid' => $previousAssigneeUuid,
                'agency_uuid' => $agency->uuid,
                'reason' => trim($reason),
            ]);

            return $lockedAssistance->load(['reservation', 'handlingAgency', 'assignedUser']);
        });
    }

    public function refuse(AssistedBooking $assistedBooking, User $actor, string $reasonCode, string $reasonNote): AssistedBooking
    {
        return DB::transaction(function () use ($assistedBooking, $actor, $reasonCode, $reasonNote): AssistedBooking {
            $reservation = Reservation::query()->whereKey($assistedBooking->reservation_id)->lockForUpdate()->firstOrFail();
            $lockedAssistance = AssistedBooking::query()->whereKey($assistedBooking->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedAssistance->status->isTerminal()) {
                throw new AssistedBookingConflictException('Ce dossier est déjà clôturé.');
            }
            $this->companyCreditService->release($reservation);
            $reservation->segments()->update([
                'status' => ReservationSegmentStatus::Cancelled,
                'released_at' => now(),
            ]);
            $reservation->update(['status' => ReservationStatus::Cancelled]);
            $lockedAssistance->update([
                'status' => AssistedBookingStatus::Refused,
                'reason_code' => $reasonCode,
                'reason_note' => trim($reasonNote),
                'refused_at' => now(),
            ]);
            $this->audit($lockedAssistance, $actor, 'refused', [
                'reason_code' => $reasonCode,
                'reason_note' => trim($reasonNote),
            ]);

            return $lockedAssistance->load(['reservation', 'handlingAgency', 'assignedUser']);
        });
    }

    /**
     * @param  list<array{passenger_index: int, seat_assignments: list<array{leg_order: int, seat_uuid: string}>}>  $seatAssignments
     */
    public function prepareAgencySale(AssistedBooking $assistedBooking, User $actor, array $seatAssignments): Reservation
    {
        $result = DB::transaction(function () use ($assistedBooking, $actor, $seatAssignments): Reservation|AssistedBooking {
            $reservation = Reservation::query()->whereKey($assistedBooking->reservation_id)->lockForUpdate()->firstOrFail();
            $lockedAssistance = AssistedBooking::query()->whereKey($assistedBooking->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedAssistance->status !== AssistedBookingStatus::InProgress || $lockedAssistance->assigned_user_id !== $actor->id) {
                throw new AssistedBookingConflictException('Ce dossier n’est pas attribué à cet agent.');
            }
            if ($reservation->status === ReservationStatus::Held && $reservation->channel === ReservationChannel::Counter) {
                return $reservation->load(['segments.seat', 'passengers']);
            }
            if (! $this->ensureNotExpired($lockedAssistance, $reservation, $actor)) {
                return $lockedAssistance;
            }

            $quote = $this->recalculateQuote($reservation);
            $reservation = $this->applyItinerarySeatHold->handle(
                $reservation,
                $quote,
                $seatAssignments,
                ReservationChannel::Counter,
                replaceRequestedSegments: true,
            );
            if ($reservation->company_account_id !== null) {
                $this->companyCreditService->reserve($reservation, $reservation->companyAccount()->firstOrFail());
            }
            $lockedAssistance->update(['current_total_amount' => $quote->total_amount]);
            $this->audit($lockedAssistance, $actor, 'sale_prepared', [
                'current_amount' => $quote->total_amount,
                'seat_codes' => $reservation->segments->pluck('seat.code')->filter()->values()->all(),
            ]);

            return $reservation;
        });

        if ($result instanceof AssistedBooking) {
            throw new AssistedBookingConflictException('Cet itinéraire n’est plus disponible à la vente.');
        }

        return $result;
    }

    public function expireDue(): int
    {
        $expired = 0;
        AssistedBooking::query()
            ->whereIn('status', [AssistedBookingStatus::Pending->value, AssistedBookingStatus::InProgress->value])
            ->orderBy('id')
            ->chunkById(100, function ($assistedBookings) use (&$expired): void {
                foreach ($assistedBookings as $assistedBooking) {
                    $changed = DB::transaction(function () use ($assistedBooking): bool {
                        $reservation = Reservation::query()->whereKey($assistedBooking->reservation_id)->lockForUpdate()->firstOrFail();
                        $lockedAssistance = AssistedBooking::query()->whereKey($assistedBooking->getKey())->lockForUpdate()->firstOrFail();
                        if (! in_array($lockedAssistance->status, [AssistedBookingStatus::Pending, AssistedBookingStatus::InProgress], true)) {
                            return false;
                        }

                        $decisions = $reservation->segments()->with('departure')->get()
                            ->pluck('departure')->unique('id')
                            ->map(fn ($departure) => $this->resolveDepartureSellability->handle($departure));
                        $unavailable = $decisions->contains(fn ($data): bool => $data->reason === SellabilityReason::Cancelled);
                        $effectiveExpiry = $decisions->min(fn ($data) => $data->saleClosesAt);
                        if (! $unavailable && $effectiveExpiry !== null && $effectiveExpiry->isFuture()) {
                            $lockedAssistance->update(['expires_at' => $effectiveExpiry]);
                            $reservation->update(['expires_at' => $effectiveExpiry]);

                            return false;
                        }

                        $status = $unavailable ? AssistedBookingStatus::Unavailable : AssistedBookingStatus::Expired;
                        $reservationStatus = $unavailable ? ReservationStatus::Unavailable : ReservationStatus::Expired;
                        $reason = $unavailable ? 'departure_cancelled' : 'commercial_cutoff';
                        $lockedAssistance->update([
                            'status' => $status,
                            'reason_code' => $reason,
                            'unavailable_at' => $unavailable ? now() : null,
                        ]);
                        $reservation->update(['status' => $reservationStatus]);
                        $reservation->segments()->update([
                            'status' => $unavailable ? ReservationSegmentStatus::Cancelled : ReservationSegmentStatus::Expired,
                            'released_at' => now(),
                        ]);
                        activity('assisted-bookings')
                            ->event($status->value)
                            ->performedOn($lockedAssistance)
                            ->withProperties(['reservation_reference' => $reservation->reference, 'reason' => $reason])
                            ->log('assisted_booking.'.$status->value);

                        return true;
                    });
                    if ($changed) {
                        $expired++;
                    }
                }
            });

        return $expired;
    }

    public function request(
        Reservation $reservation,
        User $actor,
        AssistedBookingChannel $channel,
        string $contactName,
        string $contactPhone,
        string $idempotencyKey,
    ): AssistedBooking {
        $replayed = AssistedBooking::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($replayed) {
            if ($replayed->reservation_id !== $reservation->getKey()) {
                throw ValidationException::withMessages(['idempotency_key' => 'Cette clé a déjà été utilisée pour une autre réservation.']);
            }

            return $replayed;
        }

        $this->supportChannels->ensureAvailable($channel);

        return DB::transaction(function () use ($reservation, $actor, $channel, $contactName, $contactPhone, $idempotencyKey): AssistedBooking {
            $lockedReservation = Reservation::query()
                ->whereKey($reservation->getKey())
                ->with(['segments.departure.line', 'segments.originStop', 'segments.destinationStop', 'segments.seat', 'passengers'])
                ->lockForUpdate()
                ->firstOrFail();

            $existing = AssistedBooking::query()->where('reservation_id', $lockedReservation->getKey())->lockForUpdate()->first();
            if ($existing) {
                if ($existing->idempotency_key === $idempotencyKey) {
                    return $existing;
                }

                throw ValidationException::withMessages(['reservation' => 'Cette réservation possède déjà un dossier d’assistance.']);
            }

            $this->ensureRequestable($lockedReservation);
            $sellability = $lockedReservation->segments
                ->pluck('departure')
                ->unique('id')
                ->map(fn ($departure) => $this->resolveDepartureSellability->handle($departure));

            if ($sellability->contains(fn ($data): bool => ! $data->isSellable)) {
                throw ValidationException::withMessages(['reservation' => 'Cet itinéraire n’est plus disponible à la vente.']);
            }

            $expiresAt = $sellability->min(fn ($data) => $data->saleClosesAt);
            $snapshot = $this->buildSnapshot($lockedReservation, $expiresAt->toIso8601String());
            $assistedBooking = AssistedBooking::query()->create([
                'reservation_id' => $lockedReservation->getKey(),
                'preferred_agency_id' => null,
                'origin_channel' => $lockedReservation->channel ?? ReservationChannel::Online,
                'handoff_channel' => $channel,
                'status' => AssistedBookingStatus::Pending,
                'contact_name' => trim($contactName),
                'contact_phone' => $contactPhone,
                'snapshot' => $snapshot,
                'original_total_amount' => $lockedReservation->total_amount,
                'currency' => $lockedReservation->currency,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => $expiresAt,
            ]);

            $lockedReservation->segments()
                ->whereIn('status', ReservationSegmentStatus::occupyingValues())
                ->update([
                    'status' => ReservationSegmentStatus::Requested,
                    'released_at' => now(),
                ]);
            $this->companyCreditService->release($lockedReservation);
            $lockedReservation->update([
                'status' => ReservationStatus::AssistancePending,
                'expires_at' => $expiresAt,
            ]);

            activity('assisted-bookings')
                ->event('requested')
                ->performedOn($assistedBooking)
                ->causedBy($actor)
                ->withProperties([
                    'reservation_reference' => $lockedReservation->reference,
                    'channel' => $channel->value,
                ])
                ->log('assisted_booking.requested');

            return $assistedBooking->load(['reservation', 'handlingAgency', 'assignedUser']);
        });
    }

    private function ensureRequestable(Reservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::Held || $reservation->payments()->exists()) {
            throw ValidationException::withMessages(['reservation' => 'Seule une réservation en attente de paiement peut être envoyée en assistance.']);
        }
        if ($reservation->segments->isEmpty()) {
            throw ValidationException::withMessages(['reservation' => 'La réservation ne contient aucun trajet.']);
        }
    }

    private function ensureNotExpired(AssistedBooking $assistedBooking, Reservation $reservation, User $actor): bool
    {
        $segments = $reservation->segments()->with('departure')->get();
        $sellability = $segments->pluck('departure')->unique('id')
            ->map(fn ($departure) => $this->resolveDepartureSellability->handle($departure));
        $capacityUnavailable = $segments->unique('leg_order')->contains(function ($segment) use ($reservation): bool {
            $available = $this->buildSeatAvailability
                ->handle($segment->departure, $segment->originStop, $segment->destinationStop)
                ->where('availabilityStatus', SeatAvailabilityStatus::Available)
                ->count();

            return $available < max(1, (int) $reservation->group_size);
        });
        if ($assistedBooking->expires_at->lte(now()) || $sellability->contains(fn ($data): bool => ! $data->isSellable)) {
            $unavailable = $capacityUnavailable || $sellability->contains(fn ($data): bool => $data->reason === SellabilityReason::Cancelled);
            $assistanceStatus = $unavailable ? AssistedBookingStatus::Unavailable : AssistedBookingStatus::Expired;
            $reservationStatus = $unavailable ? ReservationStatus::Unavailable : ReservationStatus::Expired;
            $segmentStatus = $unavailable ? ReservationSegmentStatus::Cancelled : ReservationSegmentStatus::Expired;
            $reason = $unavailable ? 'departure_cancelled' : 'commercial_cutoff';
            $assistedBooking->update([
                'status' => $assistanceStatus,
                'reason_code' => $reason,
                'unavailable_at' => $unavailable ? now() : null,
            ]);
            $reservation->update(['status' => $reservationStatus]);
            $reservation->segments()->update(['status' => $segmentStatus]);
            $this->audit($assistedBooking, $actor, $assistanceStatus->value, ['reason' => $reason]);

            return false;
        }

        if ($capacityUnavailable) {
            $assistedBooking->update([
                'status' => AssistedBookingStatus::Unavailable,
                'reason_code' => 'insufficient_capacity',
                'unavailable_at' => now(),
            ]);
            $reservation->update(['status' => ReservationStatus::Unavailable]);
            $reservation->segments()->update(['status' => ReservationSegmentStatus::Cancelled]);
            $this->audit($assistedBooking, $actor, 'unavailable', ['reason' => 'insufficient_capacity']);

            return false;
        }

        return true;
    }

    private function recalculateQuote(Reservation $reservation): ItineraryQuote
    {
        $segments = $reservation->segments()
            ->with(['departure.line', 'originStop', 'destinationStop'])
            ->orderBy('leg_order')
            ->get()
            ->unique('leg_order');
        if ($segments->isEmpty()) {
            throw ValidationException::withMessages(['reservation' => 'Cette réservation ne contient aucun itinéraire à reprendre.']);
        }

        $legs = array_values($segments->map(fn ($segment): ItineraryLegQuoteRequest => new ItineraryLegQuoteRequest(
            departure: $segment->departure,
            originStop: $segment->originStop,
            destinationStop: $segment->destinationStop,
            direction: $segment->direction,
        ))->all());

        return $this->calculateItineraryQuote->handle(
            legs: $legs,
            passengerCount: max(1, (int) $reservation->group_size),
            user: $reservation->payer,
        )->itineraryQuote;
    }

    /** @param array<string, mixed> $properties */
    private function audit(AssistedBooking $assistedBooking, User $actor, string $event, array $properties = []): void
    {
        activity('assisted-bookings')
            ->event($event)
            ->performedOn($assistedBooking)
            ->causedBy($actor)
            ->withProperties(array_merge([
                'agency_uuid' => $assistedBooking->handlingAgency()->value('uuid'),
                'reservation_reference' => $assistedBooking->reservation()->value('reference'),
            ], $properties))
            ->log('assisted_booking.'.$event);
    }

    /** @return array<string, mixed> */
    private function buildSnapshot(Reservation $reservation, string $expiresAt): array
    {
        return [
            'reservation_reference' => $reservation->reference,
            'legs' => $reservation->segments
                ->unique('leg_order')
                ->sortBy('leg_order')
                ->map(fn ($segment): array => [
                    'leg_order' => $segment->leg_order,
                    'departure_uuid' => $segment->departure->uuid,
                    'line' => $segment->departure->line->name,
                    'origin' => $segment->originStop->name,
                    'destination' => $segment->destinationStop->name,
                    'departure_at' => $segment->departure->scheduledDepartureAt()->toIso8601String(),
                ])->values()->all(),
            'requested_seats' => $reservation->segments->map(fn ($segment): array => [
                'passenger_index' => $segment->passenger_index,
                'leg_order' => $segment->leg_order,
                'code' => $segment->seat->code,
            ])->values()->all(),
            'passenger_count' => max($reservation->passengers->count(), (int) $reservation->group_size),
            'known_passengers' => $reservation->passengers->map(fn ($passenger): array => [
                'passenger_index' => $passenger->passenger_index,
                'first_name' => $passenger->first_name,
            ])->values()->all(),
            'amount' => $reservation->total_amount,
            'currency' => $reservation->currency,
            'valid_until' => $expiresAt,
        ];
    }
}
