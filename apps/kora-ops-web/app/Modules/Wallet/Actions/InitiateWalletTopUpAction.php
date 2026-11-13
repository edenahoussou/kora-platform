<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\WalletTopUpStatus;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTopUp;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class InitiateWalletTopUpAction
{
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly WalletSettings $settings,
        private readonly PassengerIdentityResolver $identity,
        private readonly ReconcileWalletTopUpAction $reconcile,
    ) {}

    /** @return array{topup: WalletTopUp, payment: Payment, is_replay: bool} */
    public function handle(
        Wallet $wallet,
        int $amount,
        string $method,
        string $provider,
        string $idempotencyKey,
        ?string $phone = null,
        ?string $mobileMoneyNetwork = null,
    ): array {
        $country = MobileMoneyNetwork::tryFrom($mobileMoneyNetwork ?? '')?->country();
        $normalize = fn (?string $number): ?string => $this->identity->normalizePhone($number, $country ?? $wallet->user?->country_code);
        $phone = $normalize($phone);

        [$topup, $replay] = DB::transaction(function () use ($wallet, $amount, $method, $provider, $idempotencyKey, $phone, $mobileMoneyNetwork, $normalize): array {
            $locked = Wallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();
            $scope = WalletTopUp::query()->where('wallet_id', $locked->getKey())->with('payment');
            $bound = DB::table('wallet_topup_request_keys')
                ->where('wallet_id', $locked->getKey())->where('request_key', $idempotencyKey)->value('wallet_topup_id');
            $existing = $bound !== null
                ? (clone $scope)->where('id', $bound)->firstOrFail()
                : (clone $scope)->where('idempotency_key', $idempotencyKey)->first();

            $matches = static fn (WalletTopUp $candidate): bool => $candidate->amount === $amount
                && $candidate->payment->method?->value === $method
                && $candidate->payment->provider === $provider
                && $candidate->payment->mobile_money_network?->value === $mobileMoneyNetwork
                && $normalize($candidate->payment->payer_phone) === $phone;

            if ($existing !== null) {
                if (! $matches($existing)) {
                    throw new ConflictHttpException('Cette clé de recharge a déjà été utilisée avec des paramètres différents.');
                }

                return [$existing, true];
            }

            $pending = (clone $scope)->where('status', WalletTopUpStatus::Pending)->orderBy('id')->get();
            $active = $pending->first($matches);
            if ($active !== null) {
                DB::table('wallet_topup_request_keys')->insert([
                    'wallet_id' => $locked->getKey(),
                    'request_key' => $idempotencyKey,
                    'wallet_topup_id' => $active->getKey(),
                ]);

                return [$active, true];
            }

            if ($pending->contains(fn (WalletTopUp $candidate): bool => ! $this->reconcile->awaitingInitiation($candidate->payment)
                && ($candidate->payment->provider_payload['initiation_phase'] ?? null) !== 'ready')) {
                throw new ConflictHttpException('Une recharge est en cours de vérification. Attendez son résultat avant de changer de moyen de paiement.');
            }

            $this->assertAmount($locked, $amount);
            if ($method === 'mobile_money' && $provider !== 'kkiapay' && ($phone === null || ! preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone))) {
                throw ValidationException::withMessages(['phone' => 'Le numéro Mobile Money est invalide.']);
            }
            $this->manager->assertAvailable($method, $provider, $mobileMoneyNetwork);
            $providerAmount = $this->manager->providerAmount($provider, $amount, 'XOF');

            $payment = Payment::query()->create([
                'reservation_id' => null,
                'method' => $method,
                'provider' => $provider,
                'idempotency_key' => 'topup:'.hash('sha256', $locked->uuid.'|'.$idempotencyKey),
                'amount' => $amount,
                'currency' => 'XOF',
                'provider_amount' => $providerAmount['amount'],
                'provider_currency' => $providerAmount['currency'],
                'mobile_money_network' => $mobileMoneyNetwork,
                'payer_phone' => $phone,
                'status' => PaymentStatus::Pending,
                'provider_payload' => ['purpose' => 'wallet_topup', 'initiation_phase' => 'starting'],
            ]);

            $topup = WalletTopUp::query()->create([
                'wallet_id' => $locked->getKey(),
                'payment_id' => $payment->getKey(),
                'amount' => $amount,
                'status' => WalletTopUpStatus::Pending,
                'idempotency_key' => $idempotencyKey,
                'next_reconciliation_at' => now()->addMinutes(5),
            ]);

            return [$topup->setRelation('payment', $payment), false];
        });

        if (! $replay) {
            $this->reconcile->start($topup);
        } else {
            $this->reconcile->resume($topup);
        }

        $topup->refresh()->load('payment');

        return ['topup' => $topup, 'payment' => $topup->payment()->firstOrFail(), 'is_replay' => $replay];
    }

    private function assertAmount(Wallet $wallet, int $amount): void
    {
        if (! $this->settings->walletCashEnabled) {
            throw ValidationException::withMessages(['wallet' => 'La recharge du Wallet n’est pas encore ouverte.']);
        }

        if ($amount <= 0 || ($this->settings->topupMinAmount !== null && $amount < $this->settings->topupMinAmount)) {
            throw ValidationException::withMessages(['amount' => 'Le montant de la recharge est inférieur au minimum autorisé.']);
        }

        if ($this->settings->topupMaxAmount !== null && $amount > $this->settings->topupMaxAmount) {
            throw ValidationException::withMessages(['amount' => 'Le montant maximum de recharge est de '.$this->settings->topupMaxAmount.' FCFA.']);
        }

        $cap = $this->settings->cashBalanceCap;
        $pending = $cap === null ? 0 : WalletTopUp::query()->where('wallet_id', $wallet->getKey())
            ->where('status', WalletTopUpStatus::Pending)->sum('amount');

        if ($cap !== null && (int) $wallet->cash_balance + $pending + $amount > $cap) {
            throw ValidationException::withMessages(['amount' => 'Le plafond du solde Wallet est de '.$cap.' FCFA, recharges en cours comprises.']);
        }
    }
}
