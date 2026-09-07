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
        $reservation->loadMissing(['passengers', 'payments', 'payer', 'companyAccount']);
        $payment = $reservation->payments->firstWhere('status', 'confirmed') ?? $reservation->payments->first();
        $passengers = $reservation->passengers->where('status', 'active')->values();
        $unitPrice = intdiv((int) $reservation->total_amount, max(1, $passengers->count()));
        $payer = $reservation->payer_type->value === 'company' ? $reservation->companyAccount : $reservation->payer;

        return [
            'ifu' => (string) config('emecf.default_ifu'),
            'type' => 'FV',
            'operator' => ['name' => (string) ($payer === null ? 'KORA BUS' : $payer->name)],
            'client' => array_filter([
                'name' => $payer === null ? null : $payer->name,
                'contact' => $payment->payer_phone ?? ($payer === null ? null : $payer->phone),
            ]),
            'items' => $passengers->map(fn ($passenger): array => [
                'name' => 'Billet de transport KORA BUS - '.Str::limit(trim($passenger->first_name.' '.$passenger->last_name), 70, ''),
                'price' => $unitPrice,
                'quantity' => 1,
                'taxGroup' => 'A',
            ])->all(),
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
