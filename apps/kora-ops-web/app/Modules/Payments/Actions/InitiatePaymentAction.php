<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use Illuminate\Auth\Access\AuthorizationException;
<<<<<<< HEAD
use Illuminate\Support\Facades\DB;
=======
use Illuminate\Support\Str;
>>>>>>> 752c4ce (feat: implement bus amenity management system)
use Illuminate\Validation\ValidationException;

class InitiatePaymentAction
{
    public function __construct(
        // private readonly PaymentManager $manager,
    ) {
        //
    }

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
<<<<<<< HEAD
        return DB::transaction(function () use (
            $reservation,
            $user,
            $method,
            $provider,
            $idempotencyKey,
            $phone,
            $mobileMoneyNetwork,
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

            $network = $mobileMoneyNetwork ?: ($provider === 'mtn_momo' ? 'mtn_momo' : null);
            $this->manager->assertAvailable($method, $provider, $network);

            if (blank($phone)) {
                throw ValidationException::withMessages([
                    'phone' => 'Le numéro du payeur est obligatoire pour initier un paiement Mobile Money.',
                ]);
            }

            if (blank($network)) {
                throw ValidationException::withMessages([
                    'mobile_money_network' => 'Le réseau Mobile Money est obligatoire.',
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

            if (Payment::query()
                ->where('reservation_id', $lockedReservation->getKey())
                ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Confirmed->value])
                ->exists()) {
                throw ValidationException::withMessages([
                    'reservation_uuid' => 'Cette réservation possède déjà un paiement actif.',
                ]);
            }

            $providerAmount = $this->manager->providerAmount(
                provider: $provider,
                amount: (int) $lockedReservation->total_amount,
                currency: (string) $lockedReservation->currency,
            );

            $payment = Payment::create([
                'reservation_id' => $lockedReservation->id,
                'method' => $method,
                'provider' => $provider,
                'provider_reference' => null,
                'idempotency_key' => $idempotencyKey,
                'amount' => $lockedReservation->total_amount,
                'mobile_money_network' => $network,
                'payer_phone' => $phone,
                'currency' => $lockedReservation->currency,
                'provider_currency' => $providerAmount['currency'],
                'provider_amount' => $providerAmount['amount'],
                'status' => PaymentStatus::Pending,
                'provider_payload' => [
                    'method' => $method,
                    'phone' => $phone,
                    'mobile_money_network' => $network,
                ],
            ]);

            // Call the real provider to initiate the transaction.
            if ($this->manager->supports($provider)) {
                $providerAdapter = $this->manager->resolve($provider);
                $response = $providerAdapter->initiate($payment, [
                    'method' => $method,
                    'phone' => $phone,
                    'mobile_money_network' => $network,
                ]);

                $payment->update([
                    'provider_reference' => $response->providerReference,
                    'provider_transaction_id' => $response->payload['id']
                        ?? $response->payload['transaction_id']
                        ?? $response->payload['reference_id']
                        ?? null,
                    'provider_payload' => array_merge(
                        $payment->provider_payload ?? [],
                        ['provider_response' => $response->payload],
                    ),
                ]);

                if ($response->isFailed()) {
                    $payment->update(['status' => PaymentStatus::Failed]);
                }
            }

            return [
                'payment' => $payment->fresh(['reservation.departure']),
                'is_replay' => false,
=======
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
>>>>>>> 752c4ce (feat: implement bus amenity management system)
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
