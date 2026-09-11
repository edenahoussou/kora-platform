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

        // Client fiscal : utiliser le payeur du paiement (nom + téléphone),
        // pas le payer de la réservation (qui est l'agent guichet).
        // Pour les ventes entreprise, utiliser le compte entreprise.
        // En fallback, utiliser le premier passager.
        if ($reservation->payer_type->value === 'company') {
            $clientName = $reservation->companyAccount?->name;
            $clientContact = $payment?->payer_phone;
        } else {
            $clientName = $payment?->payer_name;
            $clientContact = $payment?->payer_phone;

            // Fallback : premier passager si pas de payeur sur le paiement
            if ($clientName === null && $passengers->isNotEmpty()) {
                $firstPassenger = $passengers->first();
                $clientName = trim($firstPassenger->first_name.' '.$firstPassenger->last_name);
                $clientContact = $clientContact ?? $firstPassenger->phone;
            }
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
