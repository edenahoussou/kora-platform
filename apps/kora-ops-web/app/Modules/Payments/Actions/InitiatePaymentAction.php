<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Actions\FindOrCreateWalletAction;
use App\Modules\Wallet\Actions\PlaceWalletHoldAction;
use App\Modules\Wallet\Actions\ReleaseWalletHoldAction;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletPurchaseFunding;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletHold;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InitiatePaymentAction
{
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly WalletSettings $walletSettings,
        private readonly FindOrCreateWalletAction $findOrCreateWallet,
        private readonly PlaceWalletHoldAction $placeWalletHold,
        private readonly ReleaseWalletHoldAction $releaseWalletHold,
        private readonly WalletLedgerService $walletLedger,
        private readonly ConfirmPaymentAction $confirmPayment,
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

        $walletHoldId = null;

        try {
            $result = DB::transaction(function () use ($reservation, $user, $method, $provider, $idempotencyKey, $phone, $mobileMoneyNetwork, $walletAmount, $pointsAmount, $mobileMoneyAmount, $requestFingerprint, &$walletHoldId): array {
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

                if (! in_array($lockedReservation->status, [ReservationStatus::Held, ReservationStatus::Confirmed], true)) {
                    throw ValidationException::withMessages([
                        'reservation_uuid' => 'Cette réservation doit d\'abord être reprise avant de lancer un paiement en ligne.',
                    ]);
                }

                if ($lockedReservation->expires_at && $lockedReservation->expires_at->isPast()) {
                    throw ValidationException::withMessages([
                        'reservation_uuid' => 'La réservation a expiré.',
                    ]);
                }

                $total = (int) $lockedReservation->total_amount;
                $funding = $this->resolveSplit(
                    reservation: $lockedReservation,
                    user: $user,
                    method: $method,
                    total: $total,
                    walletAmount: $walletAmount,
                    pointsAmount: $pointsAmount,
                    mobileMoneyAmount: $mobileMoneyAmount,
                );

                $walletAmount = $funding->walletCash;
                $pointsAmount = $funding->points;
                $providerPayable = $funding->providerAmount;

                $providerKey = $method === PaymentMethod::Wallet->value ? '' : (string) $provider;
                $this->manager->assertAvailable($method, $providerKey, $mobileMoneyNetwork);
                $providerAmount = $providerKey === ''
                    ? ['amount' => 0, 'currency' => $lockedReservation->currency]
                    : $this->manager->providerAmount(
                        provider: $providerKey,
                        amount: $providerPayable,
                        currency: $lockedReservation->currency,
                    );

                $payment = Payment::create([
                    'reservation_id' => $lockedReservation->id,
                    'method' => $method,
                    'provider' => $providerKey,
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $requestFingerprint,
                    'amount' => $total,
                    'currency' => $lockedReservation->currency,
                    'provider_amount' => $providerAmount['amount'],
                    'provider_currency' => $providerAmount['currency'],
                    'mobile_money_network' => $mobileMoneyNetwork,
                    'payer_phone' => $phone,
                    'status' => PaymentStatus::Pending,
                    'wallet_funding' => $walletAmount > 0 || $pointsAmount > 0 ? $funding->toArray() : null,
                    'provider_payload' => array_filter([
                        'method' => $method,
                        'phone' => $phone,
                        'mobile_money_network' => $mobileMoneyNetwork,
                        'wallet_amount' => $walletAmount ?: null,
                        'points_amount' => $pointsAmount ?: null,
                        'mobile_money_amount' => $mobileMoneyAmount,
                    ], fn ($value): bool => $value !== null),
                ]);

                if ($walletAmount > 0 || $pointsAmount > 0) {
                    $owner = $this->walletOwner($lockedReservation, $user);
                    $wallet = $this->findOrCreateWallet->handle($owner);

                    $hold = $this->placeWalletHold->handle(
                        wallet: $wallet,
                        cashAmount: $walletAmount,
                        pointsAmount: $pointsAmount,
                        idempotencyKey: 'payment:'.$payment->uuid.':hold',
                        reservation: $lockedReservation,
                        payment: $payment,
                    );

                    $walletHoldId = $hold?->getKey();
                }

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

        if ($payment->method === PaymentMethod::Wallet) {
            return [
                'payment' => $this->confirmPayment->handle($payment, 'confirmed'),
                'is_replay' => false,
            ];
        }

        $providerResponse = $this->manager->resolve((string) $payment->provider)->initiate($payment, array_filter([
            'method' => $method,
            'phone' => $phone,
            'mobile_money_network' => $mobileMoneyNetwork,
        ], fn ($value): bool => $value !== null));

        $payment->update([
            'status' => $providerResponse->status,
            'provider_reference' => $providerResponse->providerReference,
            'provider_transaction_id' => $providerResponse->providerTransactionId,
            'provider_payload' => array_merge($payment->provider_payload ?? [], $providerResponse->payload),
            'failure_code' => $providerResponse->isFailed() ? 'provider_initiation_failed' : null,
            'failure_message' => $providerResponse->isFailed() ? $providerResponse->message : null,
        ]);

        if ($providerResponse->isFailed() && $walletHoldId !== null) {
            $hold = WalletHold::query()->whereKey($walletHoldId)->first();
            if ($hold !== null) {
                $this->releaseWalletHold->handle($hold);
            }
        }

        return [
            'payment' => $payment->fresh(['reservation.segments.departure']),
            'is_replay' => false,
        ];
    }

    /**
     * Valide la répartition points / cash Wallet / complément provider.
     */
    private function resolveSplit(
        Reservation $reservation,
        ?User $user,
        string $method,
        int $total,
        ?int $walletAmount,
        ?int $pointsAmount,
        ?int $mobileMoneyAmount,
    ): WalletPurchaseFunding {
        $walletAmount = (int) $walletAmount;
        $pointsAmount = (int) $pointsAmount;
        $funding = WalletPurchaseFunding::purchase($total, $walletAmount, $pointsAmount, $this->walletSettings->pointValueFcfa);
        $providerPayable = $funding->providerAmount;

        if ($method === PaymentMethod::Wallet->value && $providerPayable !== 0) {
            throw ValidationException::withMessages([
                'wallet_amount' => 'Le solde Wallet et les points doivent couvrir la totalité du montant.',
            ]);
        }

        if ($method !== PaymentMethod::Wallet->value && $providerPayable === 0 && ($walletAmount > 0 || $pointsAmount > 0)) {
            throw ValidationException::withMessages(['method' => 'Sélectionnez le Wallet lorsque ses soldes couvrent la totalité du billet.']);
        }

        if ($providerPayable > 0 && ! in_array($method, [PaymentMethod::MobileMoney->value, PaymentMethod::Card->value], true)) {
            throw ValidationException::withMessages([
                'method' => 'Le complément doit être réglé par Mobile Money ou carte.',
            ]);
        }

        if ($mobileMoneyAmount !== null && $mobileMoneyAmount !== $providerPayable) {
            throw ValidationException::withMessages([
                'mobile_money_amount' => 'Le montant Mobile Money doit correspondre au reste à payer du devis serveur.',
            ]);
        }

        if ($walletAmount > 0 && ! $this->walletSettings->walletCashEnabled) {
            throw ValidationException::withMessages([
                'wallet_amount' => 'Le solde Wallet n’est pas encore ouvert aux paiements.',
            ]);
        }

        if ($pointsAmount > 0) {
            if (! $this->walletSettings->walletPointsEnabled) {
                throw ValidationException::withMessages(['points_amount' => 'L’utilisation des points est désactivée.']);
            }

            if ($pointsAmount < $this->walletSettings->pointsMinSpend) {
                throw ValidationException::withMessages([
                    'points_amount' => 'Le minimum d’utilisation de points est de '.$this->walletSettings->pointsMinSpend.' points.',
                ]);
            }

            $wallet = $this->walletOwner($reservation, $user);
            $walletModel = Wallet::query()->where('user_id', $wallet->getKey())->first();
            $availablePoints = $walletModel === null
                ? 0
                : $this->walletLedger->availableBalance($walletModel, WalletBalanceType::Points);

            if ($pointsAmount > $availablePoints) {
                throw ValidationException::withMessages([
                    'points_amount' => 'Le solde de points disponible est insuffisant.',
                ]);
            }
        }

        return $funding;
    }

    private function walletOwner(Reservation $reservation, ?User $user): User
    {
        if ($reservation->payer_type !== PayerType::Personal) {
            throw ValidationException::withMessages([
                'wallet_amount' => 'Le Wallet ne peut régler qu’une réservation personnelle.',
            ]);
        }

        $owner = $reservation->payer_user_id !== null
            ? User::query()->find($reservation->payer_user_id)
            : $user;

        if ($owner === null) {
            throw ValidationException::withMessages([
                'wallet_amount' => 'Aucun titulaire de Wallet identifié pour ce paiement.',
            ]);
        }

        return $owner;
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
