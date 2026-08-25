<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
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
        ?int $walletAmount = null,
        ?int $pointsAmount = null,
    ): array {
        return DB::transaction(function () use (
            $reservation,
            $user,
            $method,
            $provider,
            $idempotencyKey,
            $phone,
            $walletAmount,
            $pointsAmount,
        ): array {
            $lockedReservation = Reservation::query()
                ->whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($user && $lockedReservation->user_id && $lockedReservation->user_id !== $user->id) {
                throw new AuthorizationException('Vous n\'êtes pas autorisé à payer cette réservation.');
            }

            if ($lockedReservation->status !== ReservationStatus::Held && $lockedReservation->status !== ReservationStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'reservation_uuid' => 'Cette réservation n\'est plus payable.',
                ]);
            }

            if ($lockedReservation->expires_at && $lockedReservation->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'reservation_uuid' => 'La réservation a expiré.',
                ]);
            }

            $existingPayment = Payment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingPayment) {
                if ($existingPayment->reservation_id !== $lockedReservation->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Cette clé d\'idempotence est déjà utilisée pour une autre réservation.',
                    ]);
                }

                return [
                    'payment' => $existingPayment->loadMissing(['reservation.departure']),
                    'is_replay' => true,
                ];
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
                'reservation_id' => $lockedReservation->id,
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'idempotency_key' => $idempotencyKey,
                'amount' => $lockedReservation->total_amount,
                'currency' => $lockedReservation->currency,
                'status' => PaymentStatus::Pending,
                'provider_payload' => array_filter([
                    'method' => $method,
                    'phone' => $phone,
                    'wallet_amount' => $walletAmount,
                    'points_amount' => $pointsAmount,
                ], fn ($value) => $value !== null),
            ]);

            return [
                'payment' => $payment->loadMissing(['reservation.departure']),
                'is_replay' => false,
            ];
        });
    }
}
