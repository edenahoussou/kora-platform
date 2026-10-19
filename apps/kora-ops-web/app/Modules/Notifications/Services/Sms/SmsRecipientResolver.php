<?php

namespace App\Modules\Notifications\Services\Sms;

use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Models\Reservation;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\Payments\Data\PaymentStatus;

final class SmsRecipientResolver
{
    public function __construct(private readonly PassengerIdentityResolver $identity) {}

    public function resolve(Reservation $reservation): ?string
    {
        $reservation->loadMissing(['payments', 'payer.preference', 'companyAccount']);

        $payment = $reservation->payments->firstWhere('status', PaymentStatus::Confirmed)
            ?? $reservation->payments->first();
        $phone = $payment?->payer_phone;

        if (blank($phone)) {
            $phone = $reservation->payer_type === PayerType::Company
                ? $reservation->companyAccount?->phone
                : $reservation->payer?->phone;
        }

        $normalized = $this->identity->normalizePhone($phone);

        if ($normalized === null) {
            return null;
        }

        $user = $this->identity->resolveByPhone($normalized);

        if ($user !== null && $user->preference?->sms_notifications === false) {
            return null;
        }

        return $normalized;
    }
}
