<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
use App\Modules\AssistedBooking\Data\AssistedBookingStatus;
use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashMovementDirection;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\CompanyAccounts\Data\CompanyCreditHoldStatus;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use App\Modules\DynamicPricing\Actions\RecordPromotionRedemptionAction;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\KoraPoints\Actions\RecordKoraPointSaleAction;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
use App\Modules\Ticketing\Models\Ticket;
use App\Modules\Wallet\Actions\DebitWalletForSaleAction;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletNotificationPublisher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordCounterSalePaymentAction
{
    public function __construct(
        private readonly RecordKoraPointSaleAction $recordKoraPointSale,
        private readonly CompanyCreditService $companyCredit,
        private readonly RecordPromotionRedemptionAction $recordPromotionRedemption,
        private readonly DebitWalletForSaleAction $debitWalletForSale,
        private readonly WalletNotificationPublisher $walletNotifications,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{reservation: Reservation, payment: Payment, tickets: Collection<int, Ticket>, fiscalInvoice: FiscalInvoice}
     */
    public function execute(Reservation $reservation, User $agent, array $attributes): array
    {
        $result = DB::transaction(function () use ($reservation, $agent, $attributes): array {
            $lockedReservation = Reservation::query()
                ->with(['segments.departure', 'segments', 'passengers', 'assistedBooking'])
                ->whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedAssistance = AssistedBooking::query()
                ->where('reservation_id', $lockedReservation->getKey())
                ->lockForUpdate()
                ->first();

            if ($agent->isKoraPointUser() && (! $agent->hasRole('kora_point_agent') || $lockedReservation->created_by_user_id !== $agent->getKey() || ! KoraPoint::query()->whereKey($lockedReservation->origin_kora_point_id ?? 0)->where('agency_id', $agent->agency_id ?? 0)->exists())) {
                throw new AuthorizationException;
            }

            if ($agent->isKoraPointUser() && blank($attributes['payer_phone'] ?? null)) {
                throw ValidationException::withMessages(['payer_phone' => 'Identifiez le téléphone du payeur initial pour la vente partenaire.']);
            }

            $idempotencyKey = (string) ($attributes['idempotency_key'] ?? '');
            $existingPayment = Payment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingPayment !== null) {
                if (! $this->matchesReplay($existingPayment, $lockedReservation, $attributes)) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Cette clé de paiement a déjà été utilisée avec un contenu différent.',
                    ]);
                }

                return $this->completedResult($lockedReservation, $existingPayment);
            }

            $this->ensureReservationCanBePaid($lockedReservation, $agent, $lockedAssistance);

            $method = PaymentMethod::from((string) $attributes['method']);
            $isPartner = $agent->isKoraPointUser();
            $cashSession = (in_array($method, [PaymentMethod::CompanyCredit, PaymentMethod::Wallet], true) || $isPartner) ? null : $this->openCashSession($agent);
            $total = (int) $lockedReservation->total_amount;
            $paymentData = $this->paymentData($method, $attributes, $lockedReservation, $agent, $cashSession, $total);

            if (Payment::query()
                ->where('reservation_id', $lockedReservation->getKey())
                ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Confirmed->value])
                ->exists()) {
                throw ValidationException::withMessages([
                    'reservation' => 'Cette réservation possède déjà un paiement.',
                ]);
            }

            $payment = Payment::query()->create($paymentData);

            if ($method === PaymentMethod::Wallet) {
                $wallet = Wallet::query()->where('uuid', (string) ($attributes['wallet_uuid'] ?? ''))->firstOrFail();
                $this->debitWalletForSale->handle(
                    wallet: $wallet,
                    cashAmount: (int) ($attributes['wallet_amount'] ?? 0),
                    pointsAmount: (int) ($attributes['points_amount'] ?? 0),
                    idempotencyKey: 'payment:'.$payment->uuid.':hold',
                    reservation: $lockedReservation,
                    payment: $payment,
                    agent: $agent,
                );
                $this->walletNotifications->counterDebit(
                    $wallet,
                    (int) $payment->amount,
                    $lockedReservation,
                    $agent,
                    'KORA-'.$payment->uuid,
                );
            }

            if ($method === PaymentMethod::CompanyCredit) {
                $creditHold = $lockedReservation->companyCreditHolds()
                    ->where('status', CompanyCreditHoldStatus::Reserved->value)
                    ->lockForUpdate()
                    ->first();
                if ($lockedReservation->payer_type->value !== 'company' || $creditHold === null) {
                    throw ValidationException::withMessages(['method' => 'Aucun crédit entreprise réservé pour cette vente.']);
                }
                $this->companyCredit->convert($creditHold, $payment);
            } elseif (! $isPartner && $cashSession !== null) {
                $movementType = $method === PaymentMethod::Cash ? 'counter_sale_cash' : 'counter_sale_mobile_money';
                CashMovement::query()->create([
                    'cash_session_id' => $cashSession->getKey(),
                    'cash_ledger_id' => $payment->cash_ledger_id,
                    'cash_register_id' => $cashSession->cash_register_id,
                    'source_type' => Payment::class,
                    'source_id' => $payment->getKey(),
                    'type' => $movementType,
                    'direction' => CashMovementDirection::In,
                    'status' => CashMovementStatus::Posted,
                    'amount' => $total,
                    'currency' => $lockedReservation->currency,
                    'description' => 'Vente guichet '.$lockedReservation->uuid,
                    'created_by' => $agent->getKey(),
                    'occurred_at' => now(),
                ]);

                $ledger = CashLedger::query()->lockForUpdate()->findOrFail($payment->cash_ledger_id);
                $ledger->increment('expected_balance', $total);

                if ($method === PaymentMethod::Cash) {
                    $cashSession->increment('total_cash', $total);
                    $cashSession->refresh();
                    $cashSession->update([
                        'expected_balance' => (int) $cashSession->opening_float + (int) $cashSession->total_cash,
                    ]);
                } else {
                    $cashSession->increment('total_mobile_money', $total);
                }
            }

            $lockedReservation->update([
                'selling_agency_id' => $cashSession->agency_id ?? $agent->agency_id,
                'status' => ReservationStatus::Paid,
                'ticket_issuance_status' => TicketIssuanceStatus::Pending,
                'ticket_issuance_error' => null,
                'draft_data' => null,
            ]);
            $lockedReservation->segments()->update(['status' => ReservationSegmentStatus::Paid]);
            $this->recordKoraPointSale->execute($lockedReservation, $payment, $agent);

            // La promotion gelée sur le devis se consomme uniquement ici, au
            // passage payé — jamais au devis ni au hold abandonné.
            $this->recordPromotionRedemption->execute($lockedReservation);

            $fiscalInvoice = FiscalInvoice::query()->firstOrCreate(
                ['reservation_id' => $lockedReservation->getKey()],
                [
                    'payment_id' => $payment->getKey(),
                    'kora_reference' => 'KORA-'.Str::upper(Str::substr(str_replace('-', '', $lockedReservation->uuid), 0, 16)),
                    'status' => FiscalInvoiceStatus::Pending,
                    'total_without_tax' => $total,
                    'total_tax' => 0,
                    'total_with_tax' => $total,
                    'tax_snapshot' => [
                        'taxRate' => 0,
                        'taxCode' => 'exempt',
                        'source' => $lockedReservation->channel === ReservationChannel::CompanyPortal
                            ? 'company_portal'
                            : 'counter_sale',
                    ],
                ],
            );

            if ($lockedAssistance !== null) {
                $lockedAssistance->update([
                    'status' => AssistedBookingStatus::Finalized,
                    'current_total_amount' => $total,
                    'finalized_at' => now(),
                    'reason_code' => null,
                    'reason_note' => null,
                ]);
                activity('assisted-bookings')
                    ->event('finalized')
                    ->performedOn($lockedAssistance)
                    ->causedBy($agent)
                    ->withProperties([
                        'reservation_reference' => $lockedReservation->reference,
                        'agency_uuid' => $agent->agency?->uuid,
                        'payment_uuid' => $payment->uuid,
                        'amount' => $total,
                        'currency' => $lockedReservation->currency,
                    ])
                    ->log('assisted_booking.finalized');
            }

            return [
                'reservation' => $lockedReservation->fresh(['segments.departure', 'segments', 'passengers']),
                'payment' => $payment->fresh(),
                'tickets' => new Collection,
                'fiscalInvoice' => $fiscalInvoice,
            ];
        });

        $result['reservation'] = $result['reservation']->fresh(['segments.departure', 'segments', 'passengers']);
        $result['tickets'] = Ticket::query()->where('reservation_id', $result['reservation']->getKey())->orderBy('id')->get();

        return $result;
    }

    /**
     * @return array{reservation: Reservation, payment: Payment, tickets: Collection<int, Ticket>, fiscalInvoice: FiscalInvoice}
     */
    private function completedResult(Reservation $reservation, Payment $payment): array
    {
        $tickets = Ticket::query()
            ->where('reservation_id', $reservation->getKey())
            ->orderBy('id')
            ->get();
        $fiscalInvoice = FiscalInvoice::query()
            ->where('reservation_id', $reservation->getKey())
            ->first();
        if ($fiscalInvoice === null) {
            $total = (int) $reservation->total_amount;
            $fiscalInvoice = FiscalInvoice::query()->create([
                'reservation_id' => $reservation->getKey(),
                'payment_id' => $payment->getKey(),
                'kora_reference' => 'KORA-'.Str::upper(Str::substr(str_replace('-', '', $reservation->uuid), 0, 16)),
                'status' => FiscalInvoiceStatus::Pending,
                'total_without_tax' => $total,
                'total_tax' => 0,
                'total_with_tax' => $total,
                'tax_snapshot' => [
                    'taxRate' => 0,
                    'taxCode' => 'exempt',
                    'source' => $reservation->channel === ReservationChannel::CompanyPortal
                        ? 'company_portal'
                        : 'counter_sale',
                ],
            ]);
        }

        return [
            'reservation' => $reservation->fresh(['segments.departure', 'segments', 'passengers']),
            'payment' => $payment->fresh(),
            'tickets' => $tickets,
            'fiscalInvoice' => $fiscalInvoice,
        ];
    }

    private function ensureReservationCanBePaid(Reservation $reservation, User $agent, ?AssistedBooking $assistedBooking): void
    {
        $isCompanyPortalOwner = $reservation->channel === ReservationChannel::CompanyPortal
            && $reservation->payer_type === PayerType::Company
            && $reservation->created_by_user_id === $agent->getKey();

        if (! $agent->can('manageCounterSale', $reservation) && ! $isCompanyPortalOwner) {
            throw ValidationException::withMessages(['reservation' => 'Cette vente n’est pas attribuée à cet agent.']);
        }
        if ($assistedBooking !== null
            && ($assistedBooking->status !== AssistedBookingStatus::InProgress
                || $assistedBooking->assigned_user_id !== $agent->id
                || $assistedBooking->preferred_agency_id !== $agent->agency_id)) {
            throw ValidationException::withMessages(['reservation' => 'Le dossier assisté n’est plus attribué à cet agent ou à cette agence.']);
        }
        if (! in_array($reservation->channel, [ReservationChannel::Counter, ReservationChannel::CompanyPortal], true)) {
            throw ValidationException::withMessages([
                'reservation' => 'Cette réservation ne peut pas être payée depuis ce parcours.',
            ]);
        }

        if (! in_array($reservation->status, [ReservationStatus::Held, ReservationStatus::Confirmed], true)) {
            throw ValidationException::withMessages([
                'reservation' => 'Cette réservation est déjà payée, ticketée ou clôturée.',
            ]);
        }

        if ($reservation->expires_at === null || $reservation->expires_at->isPast()) {
            $reservation->update([
                'status' => ReservationStatus::Expired,
                'draft_data' => null,
            ]);
            $reservation->segments()
                ->where('status', ReservationSegmentStatus::Held->value)
                ->update([
                    'status' => ReservationSegmentStatus::Expired,
                    'released_at' => now(),
                ]);

            throw ValidationException::withMessages([
                'reservation' => 'Le délai de réservation est expiré. Sélectionnez à nouveau les places.',
            ]);
        }

        $logicalPassengerCount = $reservation->segments->pluck('passenger_index')->filter(fn (mixed $index): bool => $index !== null)->unique()->count();
        $expectedPassengerCount = $logicalPassengerCount > 0 ? $logicalPassengerCount : $reservation->segments->count();
        if ($reservation->passengers->count() === 0 || $reservation->passengers->count() !== $expectedPassengerCount) {
            throw ValidationException::withMessages([
                'reservation' => 'Renseignez tous les passagers avant de confirmer le paiement.',
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function matchesReplay(Payment $payment, Reservation $reservation, array $attributes): bool
    {
        if ($payment->reservation_id !== $reservation->getKey()
            || $payment->method?->value !== (string) ($attributes['method'] ?? '')
            || (int) $payment->amount !== (int) ($attributes['amount'] ?? -1)) {
            return false;
        }

        if ($payment->method === PaymentMethod::Cash) {
            return (int) $payment->cash_received_amount === (int) ($attributes['cash_received_amount'] ?? -1);
        }

        if ($payment->method === PaymentMethod::MobileMoney) {
            return $payment->mobile_money_network?->value === (string) ($attributes['mobile_money_network'] ?? '')
                && $payment->provider_reference === trim((string) ($attributes['provider_reference'] ?? ''))
                && $payment->payer_phone === trim((string) ($attributes['payer_phone'] ?? ''));
        }

        return true;
    }

    private function openCashSession(User $agent): CashSession
    {
        if ($agent->agency_id === null) {
            throw ValidationException::withMessages([
                'cash_session' => 'Votre profil n’est rattaché à aucune agence.',
            ]);
        }

        $cashSession = CashSession::query()
            ->where('agent_id', $agent->getKey())
            ->where('agency_id', $agent->agency_id)
            ->whereDate('business_date', now()->toDateString())
            ->where('status', CashSessionStatus::Open->value)
            ->with('ledgers')
            ->lockForUpdate()
            ->first();

        if ($cashSession === null) {
            throw ValidationException::withMessages([
                'cash_session' => 'Ouvrez votre caisse avant d’enregistrer une vente guichet.',
            ]);
        }

        return $cashSession;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function paymentData(
        PaymentMethod $method,
        array $attributes,
        Reservation $reservation,
        User $agent,
        ?CashSession $cashSession,
        int $total,
    ): array {
        if ((int) ($attributes['amount'] ?? -1) !== $total) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant du paiement doit correspondre au total de la vente.',
            ]);
        }

        if ($method === PaymentMethod::CompanyCredit) {
            if ((int) ($attributes['amount'] ?? -1) !== $total) {
                throw ValidationException::withMessages(['amount' => 'Le montant du paiement doit correspondre au total de la vente.']);
            }

            return [
                'reservation_id' => $reservation->getKey(),
                'method' => $method,
                'provider' => 'company_credit',
                'idempotency_key' => (string) $attributes['idempotency_key'],
                'amount' => $total,
                'currency' => $reservation->currency,
                'status' => PaymentStatus::Confirmed,
                'confirmed_at' => now(),
                'verified_by' => $agent->getKey(),
                'verified_at' => now(),
                'provider_payload' => [
                    'channel' => $reservation->channel->value ?? 'counter_sale',
                    'source' => 'company_credit',
                ],
            ];
        }

        if ($method === PaymentMethod::Wallet) {
            $walletAmount = (int) ($attributes['wallet_amount'] ?? 0);
            $pointsAmount = (int) ($attributes['points_amount'] ?? 0);

            if ($walletAmount + $pointsAmount !== $total) {
                throw ValidationException::withMessages([
                    'wallet_amount' => 'Le solde Wallet et les points doivent couvrir la totalité de la vente.',
                ]);
            }

            return [
                'reservation_id' => $reservation->getKey(),
                'method' => $method,
                'provider' => 'wallet',
                'idempotency_key' => (string) $attributes['idempotency_key'],
                'amount' => $total,
                'currency' => $reservation->currency,
                'status' => PaymentStatus::Confirmed,
                'confirmed_at' => now(),
                'verified_by' => $agent->getKey(),
                'verified_at' => now(),
                'provider_payload' => [
                    'channel' => 'counter_sale',
                    'wallet_amount' => $walletAmount,
                    'points_amount' => $pointsAmount,
                    'wallet_uuid' => (string) ($attributes['wallet_uuid'] ?? ''),
                ],
            ];
        }

        $base = [
            'reservation_id' => $reservation->getKey(),
            'method' => $method,
            'provider' => $method === PaymentMethod::Cash ? 'cash' : (string) $attributes['mobile_money_network'],
            'idempotency_key' => (string) $attributes['idempotency_key'],
            'amount' => $total,
            'currency' => $reservation->currency,
            'status' => PaymentStatus::Confirmed,
            'confirmed_at' => now(),
            'cash_session_id' => $cashSession?->getKey(),
            'verified_by' => $agent->getKey(),
            'verified_at' => now(),
            'provider_payload' => ['manualVerification' => true, 'channel' => 'counter_sale'],
        ];

        if ($agent->isKoraPointUser()) {
            if ($method === PaymentMethod::Cash) {
                $received = (int) ($attributes['cash_received_amount'] ?? -1);

                if ($received < $total) {
                    throw ValidationException::withMessages([
                        'cash_received_amount' => 'Le montant reçu est insuffisant.',
                    ]);
                }

                return $base + [
                    'cash_received_amount' => $received,
                    'change_amount' => $received - $total,
                    'payer_name' => trim((string) ($attributes['payer_name'] ?? '')) ?: null,
                    'payer_phone' => trim((string) ($attributes['payer_phone'] ?? '')) ?: null,
                ];
            }

            if ($method === PaymentMethod::MobileMoney) {
                $network = MobileMoneyNetwork::from((string) $attributes['mobile_money_network']);
                $reference = trim((string) ($attributes['provider_reference'] ?? ''));

                if ($reference !== '' && Payment::query()
                    ->where('provider', $network->value)
                    ->where('provider_reference', $reference)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'provider_reference' => 'Cette référence Mobile Money a déjà été utilisée.',
                    ]);
                }

                return $base + [
                    'provider' => $network->value,
                    'mobile_money_network' => $network,
                    'provider_reference' => $reference,
                    'payer_phone' => trim((string) ($attributes['payer_phone'] ?? '')),
                    'payer_name' => trim((string) ($attributes['payer_name'] ?? '')) ?: null,
                ];
            }
        }

        if ($method === PaymentMethod::Cash) {
            $ledger = $cashSession->ledgers->firstWhere('kind', CashLedgerKind::Cash);
            if (! $ledger instanceof CashLedger) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Le sous-journal espèces n’est pas ouvert pour cette caisse.',
                ]);
            }

            $received = (int) ($attributes['cash_received_amount'] ?? -1);

            if ($received < $total) {
                throw ValidationException::withMessages([
                    'cash_received_amount' => 'Le montant reçu est insuffisant.',
                ]);
            }

            return $base + [
                'cash_ledger_id' => $ledger->getKey(),
                'cash_received_amount' => $received,
                'change_amount' => $received - $total,
                'payer_name' => trim((string) ($attributes['payer_name'] ?? '')) ?: null,
                'payer_phone' => trim((string) ($attributes['payer_phone'] ?? '')) ?: null,
            ];
        }

        $network = MobileMoneyNetwork::from((string) $attributes['mobile_money_network']);
        $account = AgencyPaymentAccount::query()
            ->where('uuid', (string) $attributes['agency_payment_account_uuid'])
            ->where('agency_id', $agent->agency_id)
            ->where('network', $network->value)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            throw ValidationException::withMessages([
                'agency_payment_account_uuid' => 'Le compte Mobile Money sélectionné est invalide pour votre agence.',
            ]);
        }

        $ledger = $cashSession->ledgers
            ->first(fn (CashLedger $cashLedger): bool => $cashLedger->agency_payment_account_id === $account->getKey());
        if (! $ledger instanceof CashLedger) {
            throw ValidationException::withMessages([
                'agency_payment_account_uuid' => 'Le compte Mobile Money n’est pas ouvert dans la caisse du jour.',
            ]);
        }

        $reference = trim((string) $attributes['provider_reference']);
        if (Payment::query()
            ->where('provider', $network->value)
            ->where('provider_reference', $reference)
            ->where('agency_payment_account_id', $account->getKey())
            ->exists()) {
            throw ValidationException::withMessages([
                'provider_reference' => 'Cette référence Mobile Money a déjà été utilisée pour ce compte.',
            ]);
        }

        return $base + [
            'cash_ledger_id' => $ledger->getKey(),
            'agency_payment_account_id' => $account->getKey(),
            'mobile_money_network' => $network,
            'provider_reference' => $reference,
            'payer_phone' => trim((string) $attributes['payer_phone']),
            'payer_name' => trim((string) ($attributes['payer_name'] ?? '')) ?: null,
        ];
    }
}
