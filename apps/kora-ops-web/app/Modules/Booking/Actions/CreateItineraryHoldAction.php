<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PayerContext;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Exceptions\SeatUnavailableException;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\ItineraryQuoteLeg;
use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
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
        private CalculateItineraryQuoteAction $calculateItineraryQuote,
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
            $quote = ItineraryQuote::query()
                ->with(['legs.departure', 'legs.originStop', 'legs.destinationStop'])
                ->whereKey($itineraryQuote->getKey())
                ->lockForUpdate()
                ->firstOrFail();
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

            if ($payer->companyAccount !== null && ($quote->snapshot['company_account_uuid'] ?? null) !== $payer->companyAccount->uuid) {
                $quote = $this->repriceForCompanyPayer($quote, $user, $channel, $payer->companyAccount);
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
                'promotion_id' => $quote->promotion_id,
                'promotion_discount_amount' => (int) $quote->promotion_discount_amount,
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

    /**
     * Recalcule le devis avec la remise négociée du compte : les comptes
     * entreprise n'ont pas droit à la remise groupe automatique.
     */
    private function repriceForCompanyPayer(ItineraryQuote $quote, ?User $user, ReservationChannel $channel, CompanyAccount $account): ItineraryQuote
    {
        $legs = array_values($quote->legs
            ->sortBy('leg_order')
            ->map(fn (ItineraryQuoteLeg $leg): ItineraryLegQuoteRequest => new ItineraryLegQuoteRequest(
                departure: $leg->departure,
                originStop: $leg->originStop,
                destinationStop: $leg->destinationStop,
                direction: $leg->direction,
            ))
            ->all());

        return $this->calculateItineraryQuote->handle(
            legs: $legs,
            passengerCount: (int) $quote->passenger_count,
            user: $user,
            promoCode: $quote->snapshot['promotion']['code'] ?? null,
            channel: $channel,
            companyAccount: $account,
        )->itineraryQuote;
    }
}
