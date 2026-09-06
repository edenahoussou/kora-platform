<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PayerContext;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Exceptions\SeatUnavailableException;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\BusNetwork\Data\SeatStatus;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use App\Modules\Departures\Actions\ResolveDepartureSellabilityAction;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\SeatInventory\Actions\FindOccupiedSeatIdsAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\enum_value;

class CreateSeatHoldAction
{
    public function __construct(
        private FindOccupiedSeatIdsAction $findOccupiedSeatIds,
        private ResolveDepartureSellabilityAction $resolveSellability,
        private CompanyCreditService $companyCredit,
        private DepartureStopTimeResolver $stopTimeResolver,
    ) {}

    /**
     * @param  list<string>  $seatUuids
     *
     * @throws ValidationException
     * @throws SeatUnavailableException
     */
    public function handle(
        ?User $user,
        Departure $departure,
        Stop $originStop,
        Stop $destinationStop,
        PriceQuote $priceQuote,
        array $seatUuids,
        ReservationChannel $channel = ReservationChannel::Mobile,
        ?PayerContext $payer = null,
        ?string $idempotencyKey = null,
    ): Reservation {
        $sellability = $this->resolveSellability->handle($departure);
        if (! $sellability->isSellable) {
            throw ValidationException::withMessages([
                'departure_uuid' => 'La vente est fermée pour ce départ.',
            ]);
        }

        if ($priceQuote->departure_id !== $departure->id ||
            $priceQuote->origin_stop_id !== $originStop->id ||
            $priceQuote->destination_stop_id !== $destinationStop->id) {
            throw ValidationException::withMessages([
                'price_quote_uuid' => 'Le devis tarifaire ne correspond pas au trajet sélectionné.',
            ]);
        }

        if ($priceQuote->expires_at && $priceQuote->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'price_quote_uuid' => 'Le devis tarifaire a expiré.',
            ]);
        }

        if ($originStop->line_id !== $departure->line_id || $destinationStop->line_id !== $departure->line_id) {
            throw ValidationException::withMessages([
                'origin_stop_uuid' => 'Les arrêts spécifiés n\'appartiennent pas à la ligne de ce départ.',
            ]);
        }

        if ($this->stopTimeResolver->circulationIndexOf($departure, $originStop) >= $this->stopTimeResolver->circulationIndexOf($departure, $destinationStop)) {
            throw ValidationException::withMessages([
                'destination_stop_uuid' => 'L\'arrêt de destination doit être situé après l\'arrêt d\'origine dans le sens de circulation de ce départ.',
            ]);
        }

        /** @var Collection<int, Seat> $seats */
        $seats = Seat::query()
            ->where('bus_id', $departure->bus_id)
            ->whereIn('uuid', $seatUuids)
            ->get();

        if ($seats->count() !== count(array_unique($seatUuids))) {
            throw ValidationException::withMessages([
                'seats' => 'Certains sièges demandés n\'existent pas sur ce véhicule.',
            ]);
        }

        foreach ($seats as $seat) {
            if (! $seat->sellable || enum_value($seat->status) !== SeatStatus::Active->value) {
                throw new SeatUnavailableException(
                    message: "Le siège {$seat->code} n'est pas disponible à la vente.",
                    conflictingSeatUuid: $seat->uuid,
                    seatCode: $seat->code,
                );
            }
        }

        $occupiedSeatIds = $this->findOccupiedSeatIds->handle($departure, $originStop, $destinationStop);

        foreach ($seats as $seat) {
            if ($occupiedSeatIds->contains($seat->id)) {
                throw new SeatUnavailableException(
                    message: "Le siège {$seat->code} n'est plus disponible sur ce segment.",
                    conflictingSeatUuid: $seat->uuid,
                    seatCode: $seat->code,
                );
            }
        }

        $payer ??= PayerContext::personal($user?->id);
        if ($idempotencyKey === null || trim($idempotencyKey) === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Une clé d’idempotence est obligatoire pour créer un hold.',
            ]);
        }

        try {
            return DB::transaction(function () use (
                $user,
                $departure,
                $originStop,
                $destinationStop,
                $priceQuote,
                $seats,
                $channel,
                $payer,
                $idempotencyKey,
            ): Reservation {
                $lockedDeparture = Departure::query()
                    ->whereKey($departure->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedSellability = $this->resolveSellability->handle($lockedDeparture);
                if (! $lockedSellability->isSellable) {
                    throw ValidationException::withMessages([
                        'departure_uuid' => 'La vente est fermée pour ce départ.',
                    ]);
                }

                $priceQuote->refresh();
                $existingReservation = Reservation::query()
                    ->where('created_by_user_id', $user?->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existingReservation !== null) {
                    return $existingReservation->load(['segments.departure', 'segments.seat', 'segments.originStop', 'segments.destinationStop', 'passengers']);
                }
                if (($priceQuote->expires_at !== null && $priceQuote->expires_at->lte(now())) || $lockedDeparture->line_id !== $originStop->line_id || $seats->contains(fn (Seat $seat): bool => $seat->bus_id !== $lockedDeparture->bus_id)) {
                    throw ValidationException::withMessages(['price_quote_uuid' => 'Le départ a changé. Demandez un nouveau devis et sélectionnez à nouveau les sièges.']);
                }
                $expectedPassengerCount = (int) ($priceQuote->passenger_count ?: 1);
                if ($seats->count() !== $expectedPassengerCount) {
                    throw ValidationException::withMessages([
                        'seats' => 'Le nombre de sièges doit correspondre au nombre de passagers du devis.',
                    ]);
                }
                $totalAmount = (int) ($priceQuote->total_amount ?: $priceQuote->final_amount * $seats->count());

                $reservation = Reservation::create([
                    'created_by_user_id' => $user?->id,
                    'payer_user_id' => $payer->payerUserId,
                    'payer_type' => $payer->type,
                    'company_account_id' => $payer->companyAccount?->getKey(),
                    'purchase_type' => $expectedPassengerCount >= 5 ? 'group' : 'individual',
                    'group_size' => $expectedPassengerCount,
                    'payer_snapshot' => $payer->snapshot,
                    'price_quote_id' => $priceQuote->id,
                    'idempotency_key' => $idempotencyKey,
                    'status' => ReservationStatus::Held,
                    'total_amount' => $totalAmount,
                    'currency' => $priceQuote->currency,
                    'channel' => $channel,
                    'expires_at' => now()->addMinutes(15),
                    'total_snapshot' => $priceQuote->snapshot,
                ]);

                foreach ($seats->values() as $passengerIndex => $seat) {
                    ReservationSegment::create([
                        'reservation_id' => $reservation->id,
                        'leg_order' => 1,
                        'passenger_index' => $passengerIndex,
                        'departure_id' => $lockedDeparture->id,
                        'seat_id' => $seat->id,
                        'origin_stop_id' => $originStop->id,
                        'destination_stop_id' => $destinationStop->id,
                        'origin_stop_order' => $this->stopTimeResolver->circulationIndexOf($lockedDeparture, $originStop),
                        'destination_stop_order' => $this->stopTimeResolver->circulationIndexOf($lockedDeparture, $destinationStop),
                        'status' => ReservationSegmentStatus::Held,
                    ]);
                }

                if ($payer->companyAccount !== null) {
                    $this->companyCredit->reserve($reservation, $payer->companyAccount);
                }

                return $reservation->load([
                    'segments.departure',
                    'segments.seat',
                    'segments.originStop',
                    'segments.destinationStop',
                    'passengers',
                ]);
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'reservation_segments_no_overlap') || $e->getCode() === '23P01') {
                throw new SeatUnavailableException(
                    message: 'Un des sièges sélectionnés vient d\'être réservé par un autre utilisateur.',
                    previous: $e,
                );
            }

            if (str_contains($e->getMessage(), 'reservation_segments_valid_stop_order') || $e->getCode() === '23514') {
                throw ValidationException::withMessages([
                    'price_quote_uuid' => 'La séquence des arrêts de ce départ est incompatible. Demandez un nouveau devis.',
                ]);
            }

            throw $e;
        }
    }
}
