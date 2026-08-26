<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InitiatePaymentAction
{
    /**
     * @return array{payment: Payment, is_replay: bool}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(
        Reservation $reservation,
        ?User $user,
        string $method,
        string $provider,
        string $idempotencyKey,
        ?string $phone = null,
        ?string $mobileMoneyNetwork = null,
        ?int $walletAmount = null,
        ?int $pointsAmount = null,
    ): array {
        if ($user && $reservation->user_id && $reservation->user_id !== $user->id) {
            throw new AuthorizationException('Vous n\'êtes pas autorisé à payer cette réservation.');
        }

        // Idempotency check: Return existing payment if idempotency key matches
        $existingPayment = Payment::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingPayment) {
            return [
                'payment' => $existingPayment->loadMissing(['reservation.departure']),
                'is_replay' => true,
            ];
        }

        if ($reservation->status === ReservationStatus::Paid || $reservation->status === ReservationStatus::Ticketed) {
            throw ValidationException::withMessages([
                'reservation_uuid' => 'Cette réservation a déjà été payée.',
            ]);
        }

        if ($reservation->expires_at && $reservation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'reservation_uuid' => 'La réservation a expiré.',
            ]);
        }

        $providerPrefix = match ($provider) {
            'mtn_momo' => 'MM',
            'moov_money' => 'MOOV',
            'celtiis_cash' => 'CELTIIS',
            'wave' => 'WAVE',
            'kora_wallet' => 'WALLET',
            default => 'PAY',
        };

        $providerReference = $providerPrefix.'-'.strtoupper(Str::random(8));

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'provider' => $provider,
            'provider_reference' => $providerReference,
            'idempotency_key' => $idempotencyKey,
            'amount' => $reservation->total_amount,
            'currency' => $reservation->currency,
            'status' => PaymentStatus::Pending,
            'provider_payload' => array_filter([
                'method' => $method,
                'phone' => $phone,
                'wallet_amount' => $walletAmount,
                'points_amount' => $pointsAmount,
            ], fn ($v) => $v !== null),
        ]);

        return [
            'payment' => $payment->loadMissing(['reservation.departure']),
            'is_replay' => false,
        ];
    }
}
