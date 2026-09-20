<?php

namespace App\Modules\Fiscalization\Services;

use App\Modules\Booking\Models\Reservation;
use App\Modules\DynamicPricing\Models\Promotion;
use App\Modules\Payments\Data\PaymentMethod;
use Illuminate\Support\Str;

class FiscalInvoicePayloadBuilder
{
    /** @return array<string, mixed> */
    public function build(Reservation $reservation): array
    {
        $reservation->loadMissing(['passengers', 'payments', 'payer', 'companyAccount', 'creator.agency', 'promotion']);
        $payment = $reservation->payments->firstWhere('status', 'confirmed') ?? $reservation->payments->first();
        $passengers = $reservation->passengers->where('status', 'active')->values();
        $passengerCount = max(1, $passengers->count());
        $total = (int) $reservation->total_amount;
        $unitPrice = intdiv($total, $passengerCount);
        $remainder = $total - ($unitPrice * $passengerCount);

        // Prix d'origine avant remises (promo et remise groupe) : e-MECeF
        // attend une déclaration explicite via originalPrice/priceModification.
        $originalTotal = (int) ($reservation->total_snapshot['subtotal_amount']
            ?? ($total + (int) $reservation->promotion_discount_amount + (int) ($reservation->total_snapshot['group_discount']['amount'] ?? 0)));
        $originalUnitPrice = intdiv($originalTotal, $passengerCount);
        $originalRemainder = $originalTotal - ($originalUnitPrice * $passengerCount);
        $priceModification = $this->priceModification($reservation);

        $creator = $reservation->creator;
        $payerSnapshot = is_array($reservation->payer_snapshot) ? $reservation->payer_snapshot : [];

        if ($reservation->payer_type->value === 'company') {
            $clientName = $reservation->companyAccount?->name;
            $clientContact = $payment?->payer_phone;
        } else {
            $clientName = $payment?->payer_name;
            $clientContact = $payment?->payer_phone;
        }

        $items = $passengers->map(function ($passenger, int $index) use ($unitPrice, $remainder, $originalUnitPrice, $originalRemainder, $priceModification): array {
            $price = $unitPrice + ($index < $remainder ? 1 : 0);
            $originalPrice = $originalUnitPrice + ($index < $originalRemainder ? 1 : 0);

            $item = [
                'code' => $passenger->uuid,
                'name' => 'Billet de transport KORA BUS - '.Str::limit(trim($passenger->first_name.' '.$passenger->last_name), 70, ''),
                'price' => $price,
                'quantity' => 1,
                'taxGroup' => 'A',
                'taxSpecific' => 0,
            ];

            if ($originalPrice > $price && $priceModification !== null) {
                $item['originalPrice'] = $originalPrice;
                $item['priceModification'] = $priceModification;
            }

            return $item;
        })->all();

        return [
            'ifu' => (string) config('emecf.default_ifu'),
            'type' => 'FV',
            'operator' => array_filter([
                'id' => $creator?->getKey(),
                'name' => $creator === null ? 'KORA BUS' : $creator->name,
            ], fn ($value): bool => $value !== null && $value !== ''),
            'client' => array_filter([
                'ifu' => $payerSnapshot['ifu'] ?? null,
                'name' => $clientName,
                'contact' => $clientContact,
                'address' => $payerSnapshot['address'] ?? null,
            ]),
            'items' => $items,
            'payment' => $payment === null ? [] : [[
                'name' => $this->paymentType($payment->method),
                'amount' => (int) $payment->amount,
            ]],
        ];
    }

    /**
     * Motif de la modification de prix déclaré à e-MECeF (remise promotion
     * et/ou remise groupe). Null quand aucune remise ne s'applique.
     */
    private function priceModification(Reservation $reservation): ?string
    {
        $reasons = [];

        if ((int) $reservation->promotion_discount_amount > 0) {
            $promoCode = $reservation->total_snapshot['promotion']['code']
                ?? Promotion::query()->whereKey($reservation->promotion_id)->value('code');
            $reasons[] = 'Code promo '.(string) $promoCode;
        }

        if ((int) ($reservation->total_snapshot['group_discount']['amount'] ?? 0) > 0) {
            $reasons[] = 'Remise groupe';
        }

        return $reasons === [] ? null : implode(' · ', $reasons);
    }

    private function paymentType(?PaymentMethod $method): string
    {
        return match ($method?->value) {
            'cash' => 'ESPECES', 'mobile_money' => 'MOBILEMONEY', default => 'AUTRE',
        };
    }
}
