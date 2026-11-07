<?php

namespace App\Modules\Booking\Actions;

use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\ItineraryQuoteLeg;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Booking\Services\GroupPricingService;
use App\Modules\DynamicPricing\Actions\CalculatePromotionDiscountAction;
use App\Modules\DynamicPricing\Actions\ResolvePromotionAction;
use App\Modules\Payments\Data\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applique ou retire un code promo sur une réservation encore détenue (held),
 * sans recréer le devis ni les sièges : la résolution du code s'appuie sur les
 * jambes de la réservation et le total est recalculé dans la même transaction.
 * La rédemption reste enregistrée uniquement à la confirmation du paiement.
 */
class ApplyReservationPromoCodeAction
{
    public function __construct(
        private ResolvePromotionAction $resolvePromotion,
        private CalculatePromotionDiscountAction $promotionDiscount,
        private GroupPricingService $groupPricing,
    ) {}

    /**
     * @param  string|null  $promoCode  Code à appliquer, null ou vide pour retirer la remise.
     *
     * @throws ValidationException
     */
    public function handle(Reservation $reservation, ?string $promoCode): Reservation
    {
        return DB::transaction(function () use ($reservation, $promoCode): Reservation {
            $locked = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            $fail = fn (string $message): ValidationException => ValidationException::withMessages([
                'promo_code' => $message,
            ]);

            if (! in_array($locked->status, [ReservationStatus::Held, ReservationStatus::Confirmed], true)) {
                throw $fail('Le code promo ne peut être modifié qu’avant le paiement.');
            }

            if ($locked->expires_at !== null && $locked->expires_at->isPast()) {
                throw $fail('La réservation a expiré.');
            }

            if ($locked->payments()->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Confirmed->value])->exists()) {
                throw $fail('Un paiement est déjà en cours pour cette réservation. Annulez-le avant de modifier le code promo.');
            }

            $normalizedCode = trim((string) $promoCode);
            $passengerCount = max(1, (int) $locked->group_size);
            $unitAmounts = $this->legUnitAmounts($locked);

            $resolved = $normalizedCode !== ''
                ? $this->resolvePromotion->execute($normalizedCode, $this->legs($locked), $locked->channel)
                : null;

            $discount = $resolved !== null
                ? $this->promotionDiscount->execute($resolved, $unitAmounts, $passengerCount)
                : 0;

            $pricing = $this->groupPricing->calculate(
                unitAmounts: $unitAmounts,
                passengerCount: $passengerCount,
                currency: (string) $locked->currency,
                dynamicAdjustments: $this->legDynamicAdjustments($locked, count($unitAmounts), $passengerCount),
                expiresAt: $locked->expires_at,
                promotionDiscount: $discount,
                resolvedPromotion: $resolved,
            );

            $promotion = $resolved?->promotion;
            $snapshot = $locked->total_snapshot ?? [];
            $snapshot['total_amount'] = $pricing['total_amount'];
            $snapshot['subtotal_amount'] = $pricing['subtotal_amount'];
            $snapshot['dynamic_adjustment_amount'] = $pricing['dynamic_adjustment_amount'];
            $snapshot['group'] = $pricing['group'];
            $snapshot['group_discount'] = $pricing['group_discount'];
            $snapshot['passenger_allocations'] = $pricing['passenger_allocations'];
            $snapshot['passenger_leg_allocations'] = $pricing['passenger_leg_allocations'];
            $snapshot['promotion_discount_amount'] = $discount;
            $snapshot['promotion'] = $promotion === null ? null : [
                'uuid' => $promotion->uuid,
                'code' => $promotion->code,
                'name' => $promotion->name,
                'discount_amount' => $discount,
            ];

            if (isset($snapshot['pricing_breakdown']) && is_array($snapshot['pricing_breakdown'])) {
                $snapshot['pricing_breakdown']['promotions'] = $promotion === null ? [] : [[
                    'uuid' => $promotion->uuid,
                    'code' => $promotion->code,
                    'name' => $promotion->name,
                    'benefit_type' => $promotion->benefit_type->value,
                    'benefit_value' => (float) $promotion->benefit_value,
                    'discount_amount' => $discount,
                    'eligible_leg_indexes' => $resolved->eligibleLegIndexes,
                ]];
            }

            $locked->update([
                'promotion_id' => $promotion?->getKey(),
                'promotion_discount_amount' => $discount,
                'total_amount' => $pricing['total_amount'],
                'total_snapshot' => $snapshot,
            ]);

            return $locked->refresh()->load([
                'promotion',
                'itineraryQuote',
                'segments.departure.line',
                'segments.seat',
                'segments.originStop',
                'segments.destinationStop',
                'passengers.segments.seat',
            ]);
        });
    }

    /**
     * Jambes de l'itinéraire pour la résolution du périmètre promo : jambes du
     * devis d'origine si disponible, sinon segments distincts de la réservation.
     *
     * @return list<ItineraryLegQuoteRequest>
     */
    private function legs(Reservation $reservation): array
    {
        $reservation->loadMissing([
            'itineraryQuote.legs.departure',
            'itineraryQuote.legs.originStop',
            'itineraryQuote.legs.destinationStop',
            'segments.departure',
            'segments.originStop',
            'segments.destinationStop',
        ]);

        $quoteLegs = $reservation->itineraryQuote?->legs;

        if ($quoteLegs !== null && $quoteLegs->isNotEmpty()) {
            return array_values($quoteLegs->map(fn (ItineraryQuoteLeg $leg): ItineraryLegQuoteRequest => new ItineraryLegQuoteRequest(
                departure: $leg->departure,
                originStop: $leg->originStop,
                destinationStop: $leg->destinationStop,
                direction: (string) $leg->direction,
            ))->all());
        }

        return array_values($reservation->segments
            ->unique('leg_order')
            ->sortBy('leg_order')
            ->values()
            ->map(fn (ReservationSegment $segment): ItineraryLegQuoteRequest => new ItineraryLegQuoteRequest(
                departure: $segment->departure,
                originStop: $segment->originStop,
                destinationStop: $segment->destinationStop,
                direction: (string) $segment->direction,
            ))->all());
    }

    /**
     * Montant final par passager de chaque jambe, dans l'ordre des jambes.
     *
     * @return list<int>
     *
     * @throws ValidationException
     */
    private function legUnitAmounts(Reservation $reservation): array
    {
        $reservation->loadMissing(['itineraryQuote.legs', 'priceQuote']);

        $quoteLegs = $reservation->itineraryQuote?->legs;

        if ($quoteLegs !== null && $quoteLegs->isNotEmpty()) {
            return array_values($quoteLegs->map(fn (ItineraryQuoteLeg $leg): int => (int) $leg->amount)->all());
        }

        $snapshotLegs = $reservation->total_snapshot['legs'] ?? null;

        if (is_array($snapshotLegs) && $snapshotLegs !== []) {
            return array_values(array_map(fn (array $leg): int => (int) ($leg['final_amount'] ?? 0), $snapshotLegs));
        }

        if ($reservation->priceQuote !== null) {
            return [(int) $reservation->priceQuote->final_amount];
        }

        throw ValidationException::withMessages([
            'promo_code' => 'Le tarif de cette réservation ne peut pas être recalculé.',
        ]);
    }

    /**
     * Ajustements dynamiques par jambe (par passager) — métadonnée de breakdown,
     * le total se recalcule sur les montants unitaires seuls.
     *
     * @return list<int>
     */
    private function legDynamicAdjustments(Reservation $reservation, int $legCount, int $passengerCount): array
    {
        $snapshotLegs = $reservation->total_snapshot['legs'] ?? null;

        if (is_array($snapshotLegs) && count($snapshotLegs) === $legCount) {
            return array_values(array_map(fn (array $leg): int => (int) ($leg['dynamic_adjustment_amount'] ?? 0), $snapshotLegs));
        }

        $adjustments = array_fill(0, $legCount, 0);
        $totalAdjustment = (int) ($reservation->total_snapshot['dynamic_adjustment_amount'] ?? 0);

        if ($legCount > 0 && $totalAdjustment !== 0) {
            $adjustments[0] = (int) intdiv($totalAdjustment, $passengerCount);
        }

        return $adjustments;
    }
}
