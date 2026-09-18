<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PayerContext;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Exceptions\SeatUnavailableException;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a reservation and delegates all inventory acquisition to the shared seam.
 */
class CreateItineraryHoldAction
{
    public function __construct(
        private ApplyItinerarySeatHoldAction $applySeatHold,
        private CompanyCreditService $companyCredit,
    ) {}

    /**
     * @param  list<array{passenger_index: int, seat_assignments: list<array{leg_order: int, seat_uuid: string}>}>  $seatAssignments
     *
     * @throws ValidationException
     * @throws SeatUnavailableException
     */
    public function handle(
        ?User $user,
        ItineraryQuote $itineraryQuote,
        array $seatAssignments,
        ReservationChannel $channel = ReservationChannel::Mobile,
        ?string $idempotencyKey = null,
        ?PayerContext $payer = null,
    ): Reservation {
        if ($user !== null && $itineraryQuote->user_id !== null && $itineraryQuote->user_id !== $user->id) {
            throw ValidationException::withMessages(['itinerary_quote_uuid' => 'Le devis ne vous appartient pas.']);
        }
        if ($idempotencyKey === null || trim($idempotencyKey) === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'Une clé d’idempotence est obligatoire pour créer un hold.']);
        }

        $payer ??= PayerContext::personal($user?->id);

        return DB::transaction(function () use ($user, $itineraryQuote, $seatAssignments, $channel, $idempotencyKey, $payer): Reservation {
            $quote = ItineraryQuote::query()->whereKey($itineraryQuote->getKey())->lockForUpdate()->firstOrFail();
            $existingReservation = Reservation::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('created_by_user_id', $user?->id)
                ->lockForUpdate()
                ->first();
            if ($existingReservation) {
                return $existingReservation->load([
                    'itineraryQuote', 'segments.departure', 'segments.seat',
                    'segments.originStop', 'segments.destinationStop', 'passengers',
                ]);
            }
            if ($quote->expires_at->lte(now())) {
                throw ValidationException::withMessages(['itinerary_quote_uuid' => 'Le devis d’itinéraire a expiré.']);
            }
            if ($user !== null && $quote->user_id !== null && $quote->user_id !== $user->id) {
                throw ValidationException::withMessages(['itinerary_quote_uuid' => 'Le devis ne vous appartient pas.']);
            }

            $reservation = Reservation::query()->create([
                'created_by_user_id' => $user?->id,
                'payer_type' => $payer->type,
                'payer_user_id' => $payer->payerUserId,
                'company_account_id' => $payer->companyAccount?->getKey(),
                'purchase_type' => $quote->passenger_count >= 5 ? 'group' : 'individual',
                'group_size' => $quote->passenger_count,
                'payer_snapshot' => $payer->snapshot,
                'itinerary_quote_id' => $quote->id,
                'idempotency_key' => $idempotencyKey,
                'status' => ReservationStatus::Held,
                'total_amount' => $quote->total_amount,
                'currency' => $quote->currency,
                'channel' => $channel,
                'expires_at' => $quote->expires_at,
                'total_snapshot' => $quote->snapshot,
            ]);

            $reservation = $this->applySeatHold->handle($reservation, $quote, $seatAssignments, $channel);
            if ($payer->companyAccount !== null) {
                $this->companyCredit->reserve($reservation, $payer->companyAccount);
            }

            return $reservation;
        });
    }
}
