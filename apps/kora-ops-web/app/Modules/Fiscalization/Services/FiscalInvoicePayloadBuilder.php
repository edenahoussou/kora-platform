<?php

namespace App\Modules\Fiscalization\Services;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Data\PaymentMethod;
use Illuminate\Support\Str;

class FiscalInvoicePayloadBuilder
{
    /** @return array<string, mixed> */
    public function build(Reservation $reservation): array
    {
        $reservation->loadMissing(['passengers', 'payments', 'payer', 'companyAccount', 'creator.agency']);
        $payment = $reservation->payments->firstWhere('status', 'confirmed') ?? $reservation->payments->first();
        $passengers = $reservation->passengers->where('status', 'active')->values();
        $passengerCount = max(1, $passengers->count());
        $total = (int) $reservation->total_amount;
        $unitPrice = intdiv($total, $passengerCount);
        $remainder = $total - ($unitPrice * $passengerCount);

        $creator = $reservation->creator;

        if ($reservation->payer_type->value === 'company') {
            $clientName = $reservation->companyAccount?->name;
            $clientContact = $payment?->payer_phone;
        } else {
            $clientName = $payment?->payer_name;
            $clientContact = $payment?->payer_phone;
        }

        $items = $passengers->map(fn ($passenger, int $index): array => [
            'name' => 'Billet de transport KORA BUS - '.Str::limit(trim($passenger->first_name.' '.$passenger->last_name), 70, ''),
            'price' => $unitPrice + ($index < $remainder ? 1 : 0),
            'quantity' => 1,
            'taxGroup' => 'A',
        ])->all();

        return [
            'ifu' => (string) config('emecf.default_ifu'),
            'type' => 'FV',
            'operator' => array_filter([
                'id' => $creator?->getKey(),
                'name' => $creator === null ? 'KORA BUS' : $creator->name,
            ], fn ($value): bool => $value !== null && $value !== ''),
            'client' => array_filter([
                'name' => $clientName,
                'contact' => $clientContact,
            ]),
            'items' => $items,
            'payment' => $payment === null ? [] : [[
                'name' => $this->paymentType($payment->method),
                'amount' => (int) $payment->amount,
            ]],
        ];
    }

    private function paymentType(?PaymentMethod $method): string
    {
        return match ($method?->value) {
            'cash' => 'ESPECES', 'mobile_money' => 'MOBILEMONEY', default => 'AUTRE',
        };
    }
}
