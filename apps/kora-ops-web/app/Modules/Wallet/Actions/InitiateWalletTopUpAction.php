<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use App\Modules\Settings\Settings\GeneralSettings;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\WalletTopUpStatus;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTopUp;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Initie une recharge Wallet via le cycle provider existant, sans réservation.
 */
final class InitiateWalletTopUpAction
{
    /**
     * @param  PaymentManager  $manager  Résolution des providers de paiement
     * @param  WalletSettings  $settings  Bornes et plafonds de recharge
     */
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly WalletSettings $settings,
    ) {}

    /**
     * Crée la recharge et lance le paiement provider associé.
     *
     * @param  Wallet  $wallet  Wallet à recharger
     * @param  int  $amount  Montant demandé
     * @param  string  $method  Méthode provider (mobile_money, card)
     * @param  string  $provider  Fournisseur retenu
     * @param  string  $idempotencyKey  Clé d'idempotence
     * @param  string|null  $phone  Téléphone payeur
     * @param  string|null  $mobileMoneyNetwork  Réseau Mobile Money
     * @return array{topup: WalletTopUp, payment: Payment, is_replay: bool}
     *
     * @throws ValidationException
     */
    public function handle(
        Wallet $wallet,
        int $amount,
        string $method,
        string $provider,
        string $idempotencyKey,
        ?string $phone = null,
        ?string $mobileMoneyNetwork = null,
    ): array {
        if (! $this->settings->walletCashEnabled) {
            throw ValidationException::withMessages([
                'wallet' => 'La recharge du Wallet n’est pas encore ouverte.',
            ]);
        }

        $this->assertAmount($wallet, $amount);
        $this->manager->assertAvailable($method, $provider, $mobileMoneyNetwork);

        try {
            $result = DB::transaction(function () use ($wallet, $amount, $method, $provider, $idempotencyKey, $phone, $mobileMoneyNetwork): array {
                $locked = Wallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

                $existing = WalletTopUp::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return ['topup' => $existing, 'payment' => $existing->payment, 'is_replay' => true];
                }

                $currency = app(GeneralSettings::class)->currency;
                $providerAmount = $this->manager->providerAmount($provider, $amount, $currency);

                $payment = Payment::query()->create([
                    'reservation_id' => null,
                    'method' => $method,
                    'provider' => $provider,
                    'idempotency_key' => $idempotencyKey,
                    'amount' => $amount,
                    'currency' => $currency,
                    'provider_amount' => $providerAmount['amount'],
                    'provider_currency' => $providerAmount['currency'],
                    'mobile_money_network' => $mobileMoneyNetwork,
                    'payer_phone' => $phone,
                    'status' => PaymentStatus::Pending,
                    'provider_payload' => array_filter([
                        'purpose' => 'wallet_topup',
                        'method' => $method,
                        'phone' => $phone,
                        'mobile_money_network' => $mobileMoneyNetwork,
                    ], fn (mixed $value): bool => $value !== null),
                ]);

                $topup = WalletTopUp::query()->create([
                    'wallet_id' => $locked->getKey(),
                    'payment_id' => $payment->getKey(),
                    'amount' => $amount,
                    'status' => WalletTopUpStatus::Pending,
                    'idempotency_key' => $idempotencyKey,
                ]);

                return ['topup' => $topup, 'payment' => $payment, 'is_replay' => false];
            });
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'idempotency_key')) {
                throw $exception;
            }

            $topup = WalletTopUp::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();

            return ['topup' => $topup, 'payment' => $topup->payment, 'is_replay' => true];
        }

        if ($result['is_replay']) {
            return $result;
        }

        $payment = $result['payment'];
        $providerResponse = $this->manager->resolve($provider)->initiate($payment, array_filter([
            'method' => $method,
            'phone' => $phone,
            'mobile_money_network' => $mobileMoneyNetwork,
        ], fn (mixed $value): bool => $value !== null));

        $payment->update([
            'status' => $providerResponse->status,
            'provider_reference' => $providerResponse->providerReference,
            'provider_transaction_id' => $providerResponse->providerTransactionId,
            'provider_payload' => array_merge($payment->provider_payload ?? [], $providerResponse->payload),
            'failure_code' => $providerResponse->isFailed() ? 'provider_initiation_failed' : null,
            'failure_message' => $providerResponse->isFailed() ? $providerResponse->message : null,
        ]);

        return [
            'topup' => $result['topup']->fresh(['payment']),
            'payment' => $payment->fresh(),
            'is_replay' => false,
        ];
    }

    /**
     * Applique les bornes de recharge et le plafond de solde configurés.
     *
     * @param  Wallet  $wallet  Wallet à recharger
     * @param  int  $amount  Montant demandé
     *
     * @throws ValidationException
     */
    private function assertAmount(Wallet $wallet, int $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant de la recharge doit être positif.']);
        }

        if ($this->settings->topupMinAmount !== null && $amount < $this->settings->topupMinAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant minimum de recharge est de '.$this->settings->topupMinAmount.' FCFA.',
            ]);
        }

        if ($this->settings->topupMaxAmount !== null && $amount > $this->settings->topupMaxAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant maximum de recharge est de '.$this->settings->topupMaxAmount.' FCFA.',
            ]);
        }

        $cap = $this->settings->cashBalanceCap;
        if ($cap !== null && (int) $wallet->cash_balance + $amount > $cap) {
            throw ValidationException::withMessages([
                'amount' => 'Le plafond du solde Wallet est de '.$cap.' FCFA.',
            ]);
        }
    }
}
