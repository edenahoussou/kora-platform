<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Payments\Actions\ConfirmPaymentAction;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Models\WalletTopUp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReconcileWalletTopUpAction
{
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly ConfirmPaymentAction $confirm,
    ) {}

    public function start(WalletTopUp $topup): void
    {
        Cache::lock('wallet-topup-start:'.$topup->uuid, 180)->get(function () use ($topup): void {
            $payment = $topup->payment()->firstOrFail();
            if ($payment->status !== PaymentStatus::Pending) {
                return;
            }

            try {
                $response = $this->manager->resolve($payment->provider)->initiate($payment, [
                    'method' => $payment->method?->value,
                    'phone' => $payment->payer_phone,
                    'mobile_money_network' => $payment->mobile_money_network?->value,
                ]);
            } catch (Throwable $e) {
                Log::warning('Wallet top-up initiation interrupted', [
                    'payment_uuid' => $payment->uuid,
                    'topup_uuid' => $topup->uuid,
                    'exception' => $e::class,
                ]);
                $response = new ProviderResponse(PaymentStatus::Pending, payload: ['initiation_issue' => 'provider_unavailable']);
            }

            DB::transaction(function () use ($payment, $response): void {
                $locked = Payment::query()->where('id', $payment->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->status !== PaymentStatus::Pending) {
                    return;
                }

                $issue = $response->payload['initiation_issue'] ?? null;
                $phase = $locked->provider_payload['initiation_phase'] ?? 'starting';
                if ($issue === null && ! $response->isFailed()) {
                    $phase = 'ready';
                } elseif ($phase !== 'fedapay_token_pending') {
                    $phase = 'uncertain';
                }

                $publicPayload = array_intersect_key($response->payload, array_flip(['widget', 'checkout_url']));
                $locked->update([
                    'provider_reference' => $response->providerReference ?? $locked->provider_reference,
                    'provider_transaction_id' => $response->providerTransactionId ?? $locked->provider_transaction_id,
                    'provider_payload' => array_replace($locked->provider_payload ?? [], $publicPayload, [
                        'initiation_phase' => $phase,
                    ]),
                    'failure_code' => $issue ?? ($response->isFailed() ? 'provider_initiation_failed' : null),
                ]);

                if ($response->isFailed()) {
                    $this->confirm->handle($locked, 'failed');
                }
            });
        });
    }

    /** @param array<string, mixed> $authenticatedEvent */
    public function verify(Payment $payment, ?string $transactionId = null, array $authenticatedEvent = []): Payment
    {
        if ($transactionId !== null && $authenticatedEvent !== []) {
            $this->rememberAuthenticatedCandidate($payment, $transactionId, $authenticatedEvent);
        }

        $candidate = clone $payment;
        if (in_array($payment->provider, ['kkiapay', 'fedapay'], true) && filled($transactionId)) {
            $candidate->provider_transaction_id = $transactionId;
        }

        $response = $this->manager->resolve($payment->provider)->verify($candidate);
        if ($this->shouldReopenStaleKkiapayWidget($payment, $candidate, $response)) {
            return $this->releaseStaleKkiapayTransaction($payment, $candidate->provider_transaction_id);
        }

        $data = $response->payload;
        $correlation = match ($payment->provider) {
            'fedapay' => data_get($data, 'custom_metadata.kora_payment_uuid'),
            'kkiapay' => $data['partnerId'] ?? $authenticatedEvent['partnerId'] ?? null,
            'mtn_momo' => $data['externalId'] ?? null,
            default => null,
        };
        $currency = data_get($data, 'currency.iso') ?? ($data['currency'] ?? null);
        if ($payment->provider === 'kkiapay' && $currency === null) {
            $currency = 'XOF';
        }

        $receipt = $response->providerTransactionId ?? $response->providerReference;
        $amount = filter_var($data['amount'] ?? $authenticatedEvent['amount'] ?? null, FILTER_VALIDATE_INT);
        if ($response->status === PaymentStatus::Pending && (blank($receipt) || $correlation === null)) {
            return $payment->refresh();
        }
        if ($correlation !== $payment->uuid || $amount !== (int) $payment->provider_amount
            || $currency !== $payment->provider_currency || blank($receipt) || strlen($receipt) > 128) {
            Log::warning('Wallet top-up provider verification mismatch', [
                'payment_uuid' => $payment->uuid,
                'provider' => $payment->provider,
            ]);

            return $payment->refresh();
        }

        return DB::transaction(function () use ($payment, $response, $receipt): Payment {
            $locked = Payment::query()->where('id', $payment->getKey())->lockForUpdate()->firstOrFail();
            $key = ['provider' => $locked->provider, 'transaction_reference' => $receipt];
            DB::table('wallet_topup_provider_receipts')->insertOrIgnore($key + ['payment_id' => $locked->getKey()]);
            $owner = DB::table('wallet_topup_provider_receipts')->where($key)->value('payment_id');
            if ((int) $owner !== (int) $locked->getKey()) {
                Log::warning('Wallet top-up receipt already attributed', ['payment_uuid' => $locked->uuid]);

                return $locked;
            }

            if ($locked->status->isTerminal()) {
                $recorded = $locked->provider_transaction_id ?? $locked->provider_reference;
                if ($response->isConfirmed() && ($locked->status !== PaymentStatus::Confirmed || $recorded !== $receipt)
                    && DB::table('wallet_topup_provider_receipts')->where($key)->whereNull('ops_reported_at')->update(['ops_reported_at' => now()]) > 0) {
                    DB::afterCommit(fn () => Log::critical('Wallet top-up encashment requires OPS settlement', [
                        'payment_uuid' => $locked->uuid,
                        'provider' => $locked->provider,
                        'transaction_reference' => $receipt,
                    ]));
                }

                return $locked;
            }

            if ($response->status === PaymentStatus::Pending) {
                $locked->update([
                    'provider_transaction_id' => $locked->provider_transaction_id ?? $response->providerTransactionId,
                    'last_verified_at' => now(),
                ]);

                return $locked;
            }

            $failure = $response->payload['failureCode'] ?? data_get($response->payload, 'reason.code');
            $locked->update([
                'provider_transaction_id' => $response->providerTransactionId ?? $locked->provider_transaction_id,
                'failure_code' => $response->isConfirmed() ? null : match ($failure) {
                    'insufficient_funds', 'NOT_ENOUGH_FUNDS' => 'insufficient_funds',
                    'invalid_phone_number', 'invalid_payer' => 'invalid_payment_details',
                    default => 'payment_declined',
                },
                'last_verified_at' => now(),
            ]);

            return $this->confirm->handle($locked, $response->isConfirmed() ? 'confirmed' : 'failed', $response->providerReference);
        });
    }

    /** @param array<string, mixed> $event */
    private function rememberAuthenticatedCandidate(Payment $payment, string $transactionId, array $event): void
    {
        DB::transaction(function () use ($payment, $transactionId, $event): void {
            $locked = Payment::query()->where('id', $payment->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== PaymentStatus::Pending) {
                return;
            }

            $payload = $locked->provider_payload ?? [];
            if (isset($payload['reconciliation_candidate'])) {
                return;
            }

            $payload['reconciliation_candidate'] = [
                'id' => $transactionId,
                'event' => array_intersect_key($event, array_flip(['partnerId', 'amount'])),
            ];
            $locked->update(['provider_payload' => $payload]);
        });
    }

    /**
     * Indique qu'aucun moyen de paiement client (widget, URL de paiement ou
     * référence prestataire) n'a encore été produit pour ce paiement.
     */
    public function awaitingInitiation(Payment $payment): bool
    {
        return $payment->status === PaymentStatus::Pending
            && blank($payment->provider_transaction_id)
            && blank($payment->provider_reference)
            && ! $this->hasPaymentHandoff($payment);
    }

    /**
     * Indique qu'un moyen de paiement a réellement été remis au client
     * (widget KKiaPay ou URL de paiement FedaPay).
     */
    public function hasPaymentHandoff(Payment $payment): bool
    {
        return isset($payment->provider_payload['widget']) || isset($payment->provider_payload['checkout_url']);
    }

    /**
     * Indique qu'une recharge encore en attente interdit un autre montant.
     *
     * Une initiation qui a échoué sans remettre de moyen de paiement, ou qui a
     * dépassé le délai d'initiation, ne doit plus bloquer le voyageur : FedaPay
     * et MTN MoMo écrivent une référence avant l'appel prestataire, donc une
     * référence seule ne prouve pas que le client puisse payer.
     */
    public function blocksNewTopUp(Payment $payment): bool
    {
        return $payment->status === PaymentStatus::Pending
            && ! $this->hasPaymentHandoff($payment)
            && ($payment->provider_payload['initiation_phase'] ?? null) !== 'ready'
            && ! $this->initiationFailed($payment)
            && ! $this->initiationExpired($payment);
    }

    /**
     * Classe la recharge pour la commande de rapprochement.
     *
     * @return string `confirm_topup`, `close_failed`, `expire_stale`, `awaiting_initiation` ou `verify_provider`
     */
    public function state(WalletTopUp $topup): string
    {
        $payment = $topup->payment;

        return match (true) {
            $payment->status === PaymentStatus::Confirmed => 'confirm_topup',
            $payment->status !== PaymentStatus::Pending => 'close_failed',
            ! $this->hasPaymentHandoff($payment) && ($this->initiationExpired($payment) || $this->provenLegacyFailure($payment)) => 'expire_stale',
            $this->awaitingInitiation($payment) => 'awaiting_initiation',
            default => 'verify_provider',
        };
    }

    /**
     * Relance l'initiation d'une recharge restée sans moyen de paiement, ou
     * l'expire au-delà du délai configuré afin de libérer le montant.
     */
    public function resume(WalletTopUp $topup): void
    {
        $payment = $topup->payment()->firstOrFail();

        if ($payment->status === PaymentStatus::Confirmed) {
            app(ConfirmWalletTopUpAction::class)->confirm($payment);

            return;
        }

        if ($payment->status !== PaymentStatus::Pending) {
            app(ConfirmWalletTopUpAction::class)->fail($payment);

            return;
        }

        if ($this->hasPaymentHandoff($payment)) {
            return;
        }

        if ($this->provenLegacyFailure($payment) || $this->initiationExpired($payment)) {
            $this->expireInitiation($payment);

            return;
        }

        if ($this->canReinitiate($payment)) {
            $this->start($topup);
        }
    }

    public function recover(WalletTopUp $topup): void
    {
        $payment = $topup->payment;

        if ($payment->status !== PaymentStatus::Pending || $this->awaitingInitiation($payment)) {
            $this->resume($topup);

            return;
        }

        $candidate = $payment->provider_payload['reconciliation_candidate'] ?? [];
        $payment = $this->verify($payment, $candidate['id'] ?? null, $candidate['event'] ?? []);
        if ($payment->status !== PaymentStatus::Pending || $this->hasPaymentHandoff($payment)) {
            return;
        }

        if ($this->provenLegacyFailure($payment) || $this->initiationExpired($payment)) {
            $this->expireInitiation($payment);

            return;
        }

        if ($this->canReinitiate($payment)) {
            $this->start($topup);
        }
    }

    /**
     * Une initiation déjà engagée peut être relancée sans effet de bord lorsque
     * le prestataire n'a rien remis au client : widget KKiaPay, reprise d'un
     * jeton FedaPay, ou échec d'initiation sans moyen de paiement.
     */
    private function canReinitiate(Payment $payment): bool
    {
        if ($payment->provider === 'kkiapay') {
            return true;
        }

        return $this->initiationFailed($payment)
            || ($payment->provider_payload['initiation_phase'] ?? null) === 'fedapay_token_pending';
    }

    /**
     * Indique qu'une initiation a été tentée puis abandonnée avant tout moyen
     * de paiement : le montant est toujours disponible côté client.
     */
    private function initiationFailed(Payment $payment): bool
    {
        return filled($payment->failure_code)
            || isset($payment->provider_payload['initiation_issue'])
            || filled($payment->provider_payload['error'] ?? null);
    }

    private function initiationExpired(Payment $payment): bool
    {
        $ttl = app(WalletSettings::class)->topupInitiationTtlMinutes;

        return $payment->created_at?->lte(now()->subMinutes($ttl)) === true;
    }

    private function shouldReopenStaleKkiapayWidget(
        Payment $payment,
        Payment $candidate,
        ProviderResponse $response,
    ): bool {
        $providerError = filter_var($response->payload['error'] ?? null, FILTER_VALIDATE_INT);

        return $payment->provider === 'kkiapay'
            && $payment->status === PaymentStatus::Pending
            && filled($candidate->provider_transaction_id)
            && $response->status === PaymentStatus::Pending
            && in_array($providerError, [400, 404, 422, 502, 503], true)
            && $this->initiationExpired($payment);
    }

    private function releaseStaleKkiapayTransaction(Payment $payment, string $transactionId): Payment
    {
        return DB::transaction(function () use ($payment, $transactionId): Payment {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== PaymentStatus::Pending) {
                return $locked;
            }
            if (filled($locked->provider_transaction_id) && $locked->provider_transaction_id !== $transactionId) {
                return $locked;
            }

            $payload = $locked->provider_payload ?? [];
            $staleTransactionIds = array_values(array_unique([
                ...($payload['stale_transaction_ids'] ?? []),
                $transactionId,
            ]));
            $payload['stale_transaction_ids'] = array_slice($staleTransactionIds, -10);
            unset($payload['reconciliation_candidate']);

            $locked->update([
                'provider_transaction_id' => null,
                'provider_payload' => $payload,
                'last_verified_at' => now(),
            ]);

            Log::warning('Stale KKiaPay wallet top-up transaction released for retry', [
                'payment_uuid' => $locked->uuid,
            ]);

            return $locked->refresh();
        });
    }

    private function provenLegacyFailure(Payment $payment): bool
    {
        $error = (string) ($payment->provider_payload['error'] ?? '');

        return in_array($payment->provider, ['fedapay', 'mtn_momo'], true)
            && str_contains($error, 'Attempt to read property "uuid" on null');
    }

    private function expireInitiation(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $locked = Payment::query()->where('id', $payment->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== PaymentStatus::Pending || $this->hasPaymentHandoff($locked)) {
                return;
            }

            $locked->update([
                'status' => PaymentStatus::Failed,
                'failure_code' => 'provider_initiation_failed',
                'last_verified_at' => now(),
            ]);
            app(ConfirmWalletTopUpAction::class)->fail($locked);
        });
    }
}
