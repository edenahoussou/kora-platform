<?php

namespace App\Modules\Parcels\Services;

use App\Models\User;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashMovementDirection;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\Notifications\Services\OpsNotifier;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCharge;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use App\Modules\Settings\Settings\IntegrationsSettings;
use App\Modules\Settings\Settings\OperationsSettings;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Actions\CaptureWalletHoldAction;
use App\Modules\Wallet\Actions\FindOrCreateWalletAction;
use App\Modules\Wallet\Actions\PlaceWalletHoldAction;
use App\Modules\Wallet\Actions\ReleaseWalletHoldAction;
use App\Modules\Wallet\Data\WalletHoldStatus;
use App\Modules\Wallet\Data\WalletPurchaseFunding;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\WalletHold;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ParcelPayments
{
    public function __construct(
        private ParcelAccess $access,
        private ParcelFinance $finance,
        private ParcelNotifications $notifications,
        private PaymentManager $providers,
        private WalletSettings $walletSettings,
        private PlaceWalletHoldAction $hold,
        private CaptureWalletHoldAction $capture,
        private ReleaseWalletHoldAction $release,
        private WalletLedgerService $walletLedger,
        private FindOrCreateWalletAction $wallets,
    ) {}

    /** @param array<string, mixed> $input */
    public function pay(Parcel $parcel, User $actor, array $input, bool $counter): Payment
    {
        $payment = DB::transaction(function () use ($parcel, $actor, $input, $counter): Payment {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            $hub = $parcel->corridor->originHub;
            if ($counter) {
                $this->access->atHub($actor, $hub, 'parcels.intake');
            } else {
                $this->access->require($actor, 'parcels.customer');
                abort_unless($parcel->sender_user_id === $actor->id, 404);
            }

            $charge = $parcel->charges()->where('return_leg', $parcel->status === 'returned')->whereNull('superseded_at')->latest('id')->first();
            abort_if($charge === null, 409, 'This parcel has no collectible quote.');
            $fingerprint = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
            $attempts = Payment::query()->where('parcel_charge_id', $charge->id);
            $existing = (clone $attempts)->where('idempotency_key', $input['idempotency_key'])->first();
            if ($existing !== null) {
                abort_unless($existing->request_fingerprint === $fingerprint, 409, 'This payment key has already been used.');

                return $existing;
            }
            abort_unless($this->isPayable($parcel, $charge), 409, 'This parcel quote can no longer be collected.');
            abort_if($attempts->where('status', PaymentStatus::Pending)->exists(), 409, 'A payment is already in progress.');
            abort_unless((int) $input['amount'] === (int) $charge->total, 409, 'The amount must match the latest weighed quote.');

            $method = PaymentMethod::from($input['method']);
            $walletAmount = (int) ($input['wallet_amount'] ?? 0);
            $points = (int) ($input['points_amount'] ?? 0);
            abort_if($walletAmount > 0 && ! $parcel->corridor->wallet_cash_enabled, 422, 'The parcel wallet balance is disabled.');
            $funding = WalletPurchaseFunding::purchase((int) $charge->total, $walletAmount, $points, $this->walletSettings->pointValueFcfa);
            $provider = $input['provider'] ?? '';
            $network = MobileMoneyNetwork::tryFrom($input['mobile_money_network'] ?? '');
            $cashAttributes = [];
            if ($counter) {
                abort_unless(in_array($method, [PaymentMethod::Cash, PaymentMethod::MobileMoney], true) && $walletAmount === 0 && $points === 0, 422);
                $cashAttributes = $this->counterAttributes($actor, (int) $charge->total, $method, $network, $input);
                $provider = $method === PaymentMethod::Cash ? 'cash' : $network?->value;
            } elseif ($method === PaymentMethod::Wallet) {
                abort_unless($funding->providerAmount === 0, 422, 'The wallet must cover the payment amount.');
                $provider = 'wallet';
            } else {
                abort_unless($funding->providerAmount > 0 && in_array($method, [PaymentMethod::MobileMoney, PaymentMethod::Card], true), 422);
                $country = strtoupper((string) $hub?->agency?->country);
                $network ??= $provider === 'mtn_momo' ? MobileMoneyNetwork::MtnMomo : null;
                $this->providers->assertAvailable($method->value, $provider, $network?->value);
                $networkCountry = $network !== null ? strtoupper($network->country()) : null;
                $kkiapayUnsupported = $provider === 'kkiapay'
                    && ! in_array($country, app(IntegrationsSettings::class)->kkiapayCountries, true);
                abort_if(($networkCountry !== null && $networkCountry !== $country) || $kkiapayUnsupported, 422, 'The selected payment provider is not available in this country.');
                $providerFunding = $this->providers->providerAmount($provider, $funding->providerAmount, 'XOF');
                $cashAttributes = ['provider_amount' => $providerFunding['amount'], 'provider_currency' => $providerFunding['currency']];
            }

            $payment = Payment::query()->create($cashAttributes + [
                'parcel_charge_id' => $charge->id,
                'idempotency_key' => $input['idempotency_key'],
                'request_fingerprint' => $fingerprint,
                'method' => $method,
                'provider' => $provider,
                'mobile_money_network' => $network,
                'amount' => $charge->total,
                'currency' => 'XOF',
                'status' => PaymentStatus::Pending,
                'wallet_funding' => $funding->toArray(),
                'payer_name' => $parcel->sender_name,
                'payer_phone' => $parcel->sender_phone,
            ]);

            if ($walletAmount > 0 || $points > 0) {
                $wallet = $this->wallets->handle($actor);
                $this->hold->handle($wallet, $walletAmount, $points, 'parcel:'.$payment->uuid.':hold', payment: $payment);
            }

            if ($counter || $method === PaymentMethod::Wallet) {
                $this->settle($parcel, $charge, $payment, $payment->provider_reference, [], $actor);
            }

            return $payment;
        });

        if (! $counter && $payment->method !== PaymentMethod::Wallet && $payment->wasRecentlyCreated) {
            $provider = $this->providers->resolve($payment->provider);
            try {
                $response = $provider->initiate($payment, [
                    'method' => $payment->method?->value,
                    'mobile_money_network' => $payment->mobile_money_network?->value,
                    'phone' => $payment->payer_phone,
                ]);
            } catch (Throwable $exception) {
                Log::warning('Parcel payment initiation interrupted', ['payment_uuid' => $payment->uuid, 'exception' => $exception::class]);

                return $payment->refresh();
            }

            if ($response->isFailed() && in_array($response->payload['initiation_issue'] ?? null, ['invalid_payment_details', 'provider_rejected'], true)) {
                return $this->confirm($payment, PaymentStatus::Failed, $response->providerReference, $response->payload);
            }

            return DB::transaction(function () use ($payment, $response): Payment {
                $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                if ($locked->status !== PaymentStatus::Pending) {
                    return $locked;
                }
                $locked->update([
                    'provider_transaction_id' => $locked->provider_transaction_id ?? $response->providerTransactionId,
                    'provider_reference' => $locked->provider_reference ?? $response->providerReference,
                    'provider_payload' => array_replace($locked->provider_payload ?? [], $response->payload),
                ]);

                return $locked;
            });
        }

        return $payment;
    }

    /** @param array<string, mixed> $authenticatedEvent */
    public function verify(Payment $payment, ?string $transactionId = null, array $authenticatedEvent = []): Payment
    {
        $candidate = clone $payment;
        if (in_array($payment->provider, ['kkiapay', 'fedapay'], true) && filled($transactionId)) {
            $candidate->provider_transaction_id = $transactionId;
        }

        return $this->confirmResponse($payment, $this->providers->resolve($payment->provider)->verify($candidate), $authenticatedEvent);
    }

    /** @param array<string, mixed> $authenticatedEvent */
    public function confirmResponse(Payment $payment, ProviderResponse $response, array $authenticatedEvent = []): Payment
    {
        if ($response->isPending() || $payment->provider === 'fake') {
            return $this->confirm($payment, $response->status, $response->providerReference, $response->payload);
        }

        $data = $response->payload;
        $correlation = match ($payment->provider) {
            'fedapay' => data_get($data, 'custom_metadata.kora_payment_uuid'),
            'kkiapay' => $data['partnerId'] ?? $authenticatedEvent['partnerId'] ?? null,
            'mtn_momo' => $data['externalId'] ?? null,
            default => null,
        };
        $currency = data_get($data, 'currency.iso') ?? ($data['currency'] ?? ($payment->provider === 'kkiapay' ? 'XOF' : null));
        $amount = filter_var($data['amount'] ?? $authenticatedEvent['amount'] ?? null, FILTER_VALIDATE_INT);
        $receipt = $response->providerTransactionId ?? $response->providerReference;
        if ($correlation !== $payment->uuid || $currency !== $payment->provider_currency
            || $amount !== $payment->provider_amount || blank($receipt) || strlen($receipt) > 128) {
            Log::warning('Parcel payment provider verification mismatch', ['payment_uuid' => $payment->uuid, 'provider' => $payment->provider]);

            return $payment->refresh();
        }

        return $this->confirm($payment, $response->status, $response->providerReference, $data, $receipt);
    }

    /** @param array<string, mixed> $payload */
    public function confirm(Payment $payment, PaymentStatus $status, ?string $reference, array $payload, ?string $receipt = null): Payment
    {
        $charge = ParcelCharge::query()->findOrFail($payment->parcel_charge_id);

        return DB::transaction(function () use ($payment, $charge, $status, $reference, $payload, $receipt): Payment {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($charge->parcel_id);
            $charge->refresh();
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($receipt !== null && $status === PaymentStatus::Confirmed) {
                $key = ['provider' => $payment->provider, 'transaction_reference' => $receipt];
                DB::table('payment_provider_receipts')->insertOrIgnore($key + ['payment_id' => $payment->id]);
                $owner = DB::table('payment_provider_receipts')->where($key)->value('payment_id');
                if ((int) $owner !== $payment->id) {
                    return $payment;
                }
                if ($payment->status === PaymentStatus::Confirmed || $payment->status === PaymentStatus::Refunded) {
                    if (($payment->provider_transaction_id ?? $payment->provider_reference) !== $receipt
                        && DB::table('payment_provider_receipts')->where($key)->whereNull('ops_reported_at')->update(['ops_reported_at' => now()]) > 0) {
                        $parcel->record('duplicate_provider_receipt', details: ['payment_uuid' => $payment->uuid, 'provider_receipt' => $receipt], channel: 'system');
                        DB::afterCommit(fn () => app(OpsNotifier::class)->parcelAlert($parcel, $payment->uuid.':'.$receipt, true));
                    }

                    return $payment;
                }
                $payment->update(['provider_transaction_id' => $receipt, 'last_verified_at' => now()]);
            }
            if ($payment->status === PaymentStatus::Confirmed || $payment->status === PaymentStatus::Refunded) {
                return $payment;
            }

            if ($status === PaymentStatus::Confirmed) {
                $this->settle($parcel, $charge, $payment, $reference, $payload);
            } elseif ($payment->status === PaymentStatus::Pending) {
                $payment->update([
                    'status' => $status, 'provider_reference' => $reference ?? $payment->provider_reference,
                    'provider_payload' => array_replace($payment->provider_payload ?? [], $payload),
                ]);
                if ($status->isTerminal()) {
                    $this->release->releaseForPayment($payment->id);
                }
            }

            return $payment;
        });
    }

    public function expirePending(): void
    {
        $timeout = app(OperationsSettings::class)->paymentTimeoutMinutes;
        Payment::query()->whereNotNull('parcel_charge_id')->where('status', PaymentStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes($timeout))
            ->eachById(function (Payment $payment): void {
                if ($this->providers->supports($payment->provider)) {
                    $payment = $this->verify($payment);
                }
                if ($payment->status === PaymentStatus::Pending) {
                    $this->confirm($payment, PaymentStatus::Cancelled, null, ['parcel_payment_expired' => true]);
                }
            });
    }

    private function isPayable(Parcel $parcel, ParcelCharge $charge): bool
    {
        $corridor = $parcel->corridor;
        $intakeOpen = $corridor?->enabled && $corridor->originHub?->enabled && $corridor->destinationHub?->enabled
            && $corridor->originHub->agency?->status === AgencyStatus::Active
            && $corridor->destinationHub->agency?->status === AgencyStatus::Active;

        return $charge->superseded_at === null && $charge->settled_payment_id === null
            && ! $parcel->incidents()->where('blocking', true)->whereNull('resolved_at')->exists()
            && ($charge->return_leg
                ? $parcel->status === 'returned' && $parcel->return_handed_over_at === null
                : $intakeOpen && $parcel->status === 'weighed' && $parcel->expires_at->isFuture());
    }

    /** @param array<string, mixed> $payload */
    private function settle(Parcel $parcel, ParcelCharge $charge, Payment $payment, ?string $reference, array $payload, ?User $actor = null): void
    {
        $hold = WalletHold::query()->where('payment_id', $payment->id)->lockForUpdate()->first();
        $funding = WalletPurchaseFunding::fromPayment($payment);
        $validHold = ($funding->walletCash === 0 && $funding->points === 0)
            || ($hold !== null && $hold->status === WalletHoldStatus::Active && $hold->expires_at->isFuture());
        $settle = $payment->status === PaymentStatus::Pending && $this->isPayable($parcel, $charge) && $validHold;
        $payment->update([
            'status' => PaymentStatus::Confirmed, 'confirmed_at' => now(),
            'provider_reference' => $reference ?? $payment->provider_reference,
            'provider_payload' => array_replace($payment->provider_payload ?? [], $payload),
            'failure_code' => $settle ? null : 'parcel_reconciliation_required',
        ]);

        if (! $settle) {
            $this->release->releaseForPayment($payment->id);
            $parcel->record('payment_reconciliation_required', details: ['payment_uuid' => $payment->uuid], channel: 'system');
            DB::afterCommit(fn () => app(OpsNotifier::class)->parcelAlert($parcel, $payment->uuid, true));

            return;
        }

        if ($hold !== null) {
            $this->capture->handle($hold, $payment);
        }
        $charge->update(['settled_payment_id' => $payment->id]);
        $hub = $parcel->corridor->originHub;
        if (! $charge->return_leg) {
            $this->register($parcel, (int) $hub?->id);
            if ($parcel->sender_user_id !== null && $this->walletSettings->walletPointsEnabled) {
                $wallet = $this->wallets->handle(User::query()->findOrFail($parcel->sender_user_id));
                $earned = intdiv(((int) $charge->total - $funding->pointsValue) * 2, 100);
                $this->walletLedger->creditPointsWithCompensation($wallet, $earned, WalletTransactionReason::PointsEarn,
                    'parcel:'.$payment->uuid.':earn', reference: $payment);
            }
            $parcel->record('registered', $actor, $hub, channel: $actor === null ? 'payment' : 'ops');
            $this->notifications->milestone($parcel, 'registered');
        } else {
            $parcel->record('return_fee_paid', $actor, $hub, channel: $actor === null ? 'payment' : 'ops');
        }

        if ($payment->cash_ledger_id !== null && $actor !== null) {
            $this->postCounter($payment, $actor);
        }
        $this->finance->invoice($parcel, $charge, $payment);
    }

    private function register(Parcel $parcel, int $hubId): void
    {
        if ($parcel->reference === null) {
            app(ParcelReference::class)->assign($parcel);
        }

        $parcel->update(['status' => 'registered', 'custody_hub_id' => $hubId]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function counterAttributes(User $actor, int $total, PaymentMethod $method, ?MobileMoneyNetwork $network, array $input): array
    {
        $session = CashSession::query()->where('agent_id', $actor->id)->where('agency_id', $actor->agency_id)
            ->whereDate('business_date', now()->toDateString())->where('status', CashSessionStatus::Open)->lockForUpdate()->first();
        if ($session === null) {
            throw ValidationException::withMessages(['cash_session' => 'Open your cash register before collecting a payment.']);
        }
        $ledger = CashLedger::query()->where('cash_session_id', $session->id);
        $extra = [];
        if ($method === PaymentMethod::Cash) {
            $received = (int) ($input['cash_received_amount'] ?? 0);
            abort_if($received < $total, 422, 'The amount received is insufficient.');
            $ledger->where('kind', CashLedgerKind::Cash);
            $extra = ['cash_received_amount' => $received, 'change_amount' => $received - $total];
        } else {
            abort_unless($network !== null && filled($input['provider_reference'] ?? null), 422);
            $account = AgencyPaymentAccount::query()->where('uuid', $input['agency_payment_account_uuid'] ?? '')
                ->where('agency_id', $actor->agency_id)->where('network', $network)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $ledger->where('agency_payment_account_id', $account->id);
            $reference = $input['provider_reference'];
            abort_if(Payment::query()->where('agency_payment_account_id', $account->id)->where('provider', $network->value)->where('provider_reference', $reference)->exists(), 409);
            $extra = ['agency_payment_account_id' => $account->id, 'provider_reference' => $reference];
        }

        return $extra + [
            'cash_session_id' => $session->id, 'cash_ledger_id' => $ledger->where('status', 'open')->lockForUpdate()->firstOrFail()->id,
            'verified_by' => $actor->id, 'verified_at' => now(),
        ];
    }

    private function postCounter(Payment $payment, User $actor): void
    {
        $session = CashSession::query()->findOrFail($payment->cash_session_id);
        $ledger = CashLedger::query()->findOrFail($payment->cash_ledger_id);
        CashMovement::query()->create([
            'cash_session_id' => $session->id, 'cash_register_id' => $session->cash_register_id, 'cash_ledger_id' => $ledger->id,
            'source_type' => Payment::class, 'source_id' => $payment->id,
            'type' => $payment->method === PaymentMethod::Cash ? 'counter_sale_cash' : 'counter_sale_mobile_money',
            'direction' => CashMovementDirection::In, 'status' => CashMovementStatus::Posted, 'amount' => $payment->amount,
            'currency' => 'XOF', 'description' => 'Vente KORA Colis', 'created_by' => $actor->id, 'occurred_at' => now(),
        ]);
        $ledger->increment('expected_balance', $payment->amount);
        if ($payment->method === PaymentMethod::Cash) {
            $session->increment('total_cash', $payment->amount);
            $session->increment('expected_balance', $payment->amount);
        } else {
            $session->increment('total_mobile_money', $payment->amount);
        }
    }
}
