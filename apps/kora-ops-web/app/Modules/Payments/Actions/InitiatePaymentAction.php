<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InitiatePaymentAction
{
    public function __construct(
        private readonly PaymentManager $manager,
    ) {}

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

            $payment = Payment::create([
                'reservation_id' => $lockedReservation->id,
                'provider' => $provider,
                'provider_reference' => null,
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

            // Call the real provider to initiate the transaction.
            if ($this->manager->supports($provider)) {
                $providerAdapter = $this->manager->resolve($provider);
                $response = $providerAdapter->initiate($payment, [
                    'method' => $method,
                    'phone' => $phone,
                ]);

                $payment->update([
                    'provider_reference' => $response->providerReference,
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
            ];
        });
    }
}
