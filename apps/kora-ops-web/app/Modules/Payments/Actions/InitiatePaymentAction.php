<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
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
        ?string $provider,
        string $idempotencyKey,
        ?string $phone = null,
        ?string $mobileMoneyNetwork = null,
        ?int $walletAmount = null,
        ?int $pointsAmount = null,
        ?int $mobileMoneyAmount = null,
    ): array {
        if ($user && $reservation->created_by_user_id && $reservation->created_by_user_id !== $user->id && $reservation->payer_user_id !== $user->id) {
            throw new AuthorizationException('Vous n\'êtes pas autorisé à payer cette réservation.');
        }

        $requestFingerprint = $this->fingerprint(
            method: $method,
            provider: $provider,
            phone: $phone,
            mobileMoneyNetwork: $mobileMoneyNetwork,
            walletAmount: $walletAmount,
            pointsAmount: $pointsAmount,
            mobileMoneyAmount: $mobileMoneyAmount,
        );

        try {
            $result = DB::transaction(function () use ($reservation, $method, $provider, $idempotencyKey, $phone, $mobileMoneyNetwork, $walletAmount, $pointsAmount, $mobileMoneyAmount, $requestFingerprint): array {
                $lockedReservation = Reservation::query()
                    ->whereKey($reservation->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingPayment = Payment::query()
                    ->where('reservation_id', $lockedReservation->getKey())
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingPayment !== null) {
                    if ($existingPayment->request_fingerprint === null || $existingPayment->request_fingerprint !== $requestFingerprint) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'Cette clé de paiement a déjà été utilisée avec un contenu différent.',
                        ]);
                    }

                    return [
                        'payment' => $existingPayment->loadMissing(['reservation.segments.departure']),
                        'is_replay' => true,
                    ];
                }

                if ($lockedReservation->status === ReservationStatus::Paid || $lockedReservation->status === ReservationStatus::Ticketed) {
                    throw ValidationException::withMessages([
                        'reservation_uuid' => 'Cette réservation a déjà été payée.',
                    ]);
                }

                if ($lockedReservation->expires_at && $lockedReservation->expires_at->isPast()) {
                    throw ValidationException::withMessages([
                        'reservation_uuid' => 'La réservation a expiré.',
                    ]);
                }

                if ($mobileMoneyAmount !== null && $mobileMoneyAmount !== (int) $lockedReservation->total_amount) {
                    throw ValidationException::withMessages([
                        'mobile_money_amount' => 'Le montant Mobile Money doit correspondre au total du devis serveur.',
                    ]);
                }

                $providerKey = $provider ?? '';
                $this->manager->assertAvailable($method, $providerKey, $mobileMoneyNetwork);
                $providerAmount = $this->manager->providerAmount(
                    provider: $providerKey,
                    amount: (int) $lockedReservation->total_amount,
                    currency: $lockedReservation->currency,
                );

                $payment = Payment::create([
                    'reservation_id' => $lockedReservation->id,
                    'method' => $method,
                    'provider' => $providerKey,
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $requestFingerprint,
                    'amount' => $lockedReservation->total_amount,
                    'currency' => $lockedReservation->currency,
                    'provider_amount' => $providerAmount['amount'],
                    'provider_currency' => $providerAmount['currency'],
                    'mobile_money_network' => $mobileMoneyNetwork,
                    'payer_phone' => $phone,
                    'status' => PaymentStatus::Pending,
                    'provider_payload' => array_filter([
                        'method' => $method,
                        'phone' => $phone,
                        'mobile_money_network' => $mobileMoneyNetwork,
                        'wallet_amount' => $walletAmount,
                        'points_amount' => $pointsAmount,
                        'mobile_money_amount' => $mobileMoneyAmount,
                    ], fn ($value): bool => $value !== null),
                ]);

                return [
                    'payment' => $payment->loadMissing(['reservation.segments.departure']),
                    'is_replay' => false,
                ];
            });
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'payments_reservation_idempotency_unique')) {
                throw $exception;
            }

            $payment = Payment::query()
                ->where('reservation_id', $reservation->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();

            if ($payment->request_fingerprint === null || $payment->request_fingerprint !== $requestFingerprint) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Cette clé de paiement a déjà été utilisée avec un contenu différent.',
                ]);
            }

            return [
                'payment' => $payment->loadMissing(['reservation.segments.departure']),
                'is_replay' => true,
            ];
        }

        if ($result['is_replay']) {
            return $result;
        }

        $payment = $result['payment'];
        $providerResponse = $this->manager->resolve((string) $payment->provider)->initiate($payment, array_filter([
            'method' => $method,
            'phone' => $phone,
            'mobile_money_network' => $mobileMoneyNetwork,
        ], fn ($value): bool => $value !== null));

        $payment->update([
            'status' => $providerResponse->status,
            'provider_reference' => $providerResponse->providerReference,
            'provider_payload' => array_merge($payment->provider_payload ?? [], $providerResponse->payload),
            'failure_code' => $providerResponse->isFailed() ? 'provider_initiation_failed' : null,
            'failure_message' => $providerResponse->isFailed() ? $providerResponse->message : null,
        ]);

        return [
            'payment' => $payment->fresh(['reservation.segments.departure']),
            'is_replay' => false,
        ];
    }

    private function fingerprint(
        string $method,
        ?string $provider,
        ?string $phone,
        ?string $mobileMoneyNetwork,
        ?int $walletAmount,
        ?int $pointsAmount,
        ?int $mobileMoneyAmount,
    ): string {
        return hash('sha256', (string) json_encode([
            'method' => $method,
            'provider' => $provider,
            'phone' => $phone,
            'mobile_money_network' => $mobileMoneyNetwork,
            'wallet_amount' => $walletAmount,
            'points_amount' => $pointsAmount,
            'mobile_money_amount' => $mobileMoneyAmount,
        ], JSON_THROW_ON_ERROR));
    }
}
