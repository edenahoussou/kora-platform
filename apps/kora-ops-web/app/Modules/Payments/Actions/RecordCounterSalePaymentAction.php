<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
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
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordCounterSalePaymentAction
{
    public function __construct(
        private readonly CompanyCreditService $companyCredit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{reservation: Reservation, payment: Payment, tickets: Collection<int, Ticket>, fiscalInvoice: FiscalInvoice}
     */
    public function execute(Reservation $reservation, User $agent, array $attributes): array
    {
        $result = DB::transaction(function () use ($reservation, $agent, $attributes): array {
            $lockedReservation = Reservation::query()
                ->with(['segments.departure', 'segments', 'passengers'])
                ->whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

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

            $this->ensureReservationCanBePaid($lockedReservation, $agent);

            $method = PaymentMethod::from((string) $attributes['method']);
            $cashSession = $method === PaymentMethod::CompanyCredit ? null : $this->openCashSession($agent);
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

            if ($method !== PaymentMethod::CompanyCredit) {
                $movementType = $method === PaymentMethod::Cash ? 'counter_sale_cash' : 'counter_sale_mobile_money';
                CashMovement::query()->create([
                    'cash_session_id' => $cashSession->getKey(),
                    'cash_ledger_id' => $payment->cash_ledger_id,
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
            } else {
                $creditHold = $lockedReservation->companyCreditHolds()
                    ->where('status', CompanyCreditHoldStatus::Reserved->value)
                    ->lockForUpdate()
                    ->first();
                if ($lockedReservation->payer_type->value !== 'company' || $creditHold === null) {
                    throw ValidationException::withMessages(['method' => 'Aucun crédit entreprise réservé pour cette vente.']);
                }
                $this->companyCredit->convert($creditHold, $payment);
            }

            $lockedReservation->update([
                'status' => ReservationStatus::Paid,
                'ticket_issuance_status' => TicketIssuanceStatus::Pending,
                'ticket_issuance_error' => null,
                'draft_data' => null,
            ]);
            $lockedReservation->segments()->update(['status' => ReservationSegmentStatus::Paid]);

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
                        'source' => 'counter_sale',
                    ],
                ],
            );

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
                'tax_snapshot' => ['taxRate' => 0, 'taxCode' => 'exempt', 'source' => 'counter_sale'],
            ]);
        }

        return [
            'reservation' => $reservation->fresh(['segments.departure', 'segments', 'passengers']),
            'payment' => $payment->fresh(),
            'tickets' => $tickets,
            'fiscalInvoice' => $fiscalInvoice,
        ];
    }

    private function ensureReservationCanBePaid(Reservation $reservation, User $agent): void
    {
        if ($reservation->channel?->value !== 'counter') {
            throw ValidationException::withMessages([
                'reservation' => 'Cette réservation ne provient pas du guichet.',
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
                'provider_payload' => ['channel' => 'counter_sale', 'source' => 'company_credit'],
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
