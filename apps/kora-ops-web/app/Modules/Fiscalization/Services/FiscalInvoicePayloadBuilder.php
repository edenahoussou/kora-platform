<?php

namespace App\Modules\Fiscalization\Services;

use App\Modules\Booking\Actions\DescribeReservationDiscountAction;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Support\Str;

class FiscalInvoicePayloadBuilder
{
    /** @return array<string, mixed> */
    public function build(Reservation $reservation): array
    {
        $reservation->loadMissing([
            'passengers.segments.originStop.physicalStop',
            'passengers.segments.destinationStop.physicalStop',
            'passengers.segments.seat',
            'payments',
            'payer',
            'companyAccount',
            'creator.agency',
            'promotion',
        ]);
        $payment = $reservation->payments->firstWhere('status', 'confirmed') ?? $reservation->payments->first();
        $passengers = $reservation->passengers->where('status', 'active')->values();
        $passengerCount = max(1, $passengers->count());
        $total = (int) $reservation->total_amount;
        $unitPrice = intdiv($total, $passengerCount);
        $remainder = $total - ($unitPrice * $passengerCount);

        // e-MECeF needs the pre-discount price and the applied discount reason.
        $discounts = app(DescribeReservationDiscountAction::class);
        $originalTotal = $discounts->originalTotal($reservation);
        $originalUnitPrice = intdiv($originalTotal, $passengerCount);
        $originalRemainder = $originalTotal - ($originalUnitPrice * $passengerCount);
        $priceModification = $discounts->execute($reservation);

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
                'name' => $this->itemName($passenger),
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
            'ifu' => app(IntegrationsSettings::class)->emecfDefaultIfu,
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

    /** "Réservation billet COT→PAR · siège 1A · Jean Dupont" — one line per leg. */
    private function itemName(ReservationPassenger $passenger): string
    {
        $name = Str::limit(trim($passenger->first_name.' '.$passenger->last_name), 40, '');
        $legs = $passenger->segments->sortBy('leg_order')->map(function ($segment): string {
            $origin = $segment->originStop->physicalStop->code ?? $segment->originStop->city;
            $destination = $segment->destinationStop->physicalStop->code ?? $segment->destinationStop->city;
            $seat = $segment->seat->code;

            return "{$origin}→{$destination} siège {$seat}";
        });

        return Str::limit(
            'Réservation billet '.$legs->implode(' / ').' · '.$name,
            100,
            '',
        );
    }

    private function paymentType(?PaymentMethod $method): string
    {
        return match ($method?->value) {
            'cash' => 'ESPECES', 'mobile_money' => 'MOBILEMONEY', default => 'AUTRE',
        };
    }
}
