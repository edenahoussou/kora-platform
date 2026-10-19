<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashLedgerStatus;
use App\Modules\Cash\Data\CashMovementDirection;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class KoraCashHistoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::query()->where('code', 'CTN')->first() ?? Agency::query()->first();
        if (! $agency) {
            return;
        }

        $manager = User::query()->where('email', 'manager.adjoua@kora.demo')->first() ?? User::query()->first();
        $sandrine = User::query()->where('email', 'agent.sandrine@kora.demo')->first() ?? User::query()->first();
        $brice = User::query()->where('email', 'agent.brice@kora.demo')->first() ?? User::query()->first();

        if ($manager) {
            $manager->update(['agency_id' => $agency->getKey()]);
        }
        if ($sandrine) {
            $sandrine->update(['agency_id' => $agency->getKey()]);
        }
        if ($brice) {
            $brice->update(['agency_id' => $agency->getKey()]);
        }

        $register = CashRegister::query()->where('agency_id', $agency->getKey())->where('kind', 'counter')->first();

        // 1. Session ouverte du jour pour Sandrine avec reversement en attente
        $this->seedTodayOpenSession($agency, $sandrine, $register);

        // 2. Session clôturée équilibrée d'hier pour Sandrine
        $this->seedYesterdayClosedSession($agency, $sandrine, $register);

        // 3. Session avec écart en attente d'approbation pour Brice (il y a 2 jours)
        $this->seedPendingApprovalSession($agency, $brice, $register);

        // 4. Session avec écart approuvé pour Sandrine (il y a 3 jours)
        $this->seedApprovedVarianceSession($agency, $sandrine, $manager, $register);

        // 5. Session zombie / stale ouverte (il y a 4 jours) pour tester la clôture forcée
        $this->seedStaleOpenSession($agency, $brice, $register);

        // 6. Session déjà clôturée de force (il y a 5 jours)
        $this->seedForceClosedSession($agency, $brice, $manager, $register);
    }

    private function seedTodayOpenSession(Agency $agency, User $agent, ?CashRegister $register): void
    {
        $today = now()->toDateString();

        CashSession::query()
            ->where('agent_id', $agent->getKey())
            ->where('status', CashSessionStatus::Open->value)
            ->whereDate('business_date', '!=', $today)
            ->update([
                'status' => CashSessionStatus::Closed->value,
                'closed_at' => now()->subDay(),
            ]);

        $session = CashSession::query()->firstOrCreate(
            [
                'agent_id' => $agent->getKey(),
                'agency_id' => $agency->getKey(),
                'business_date' => $today,
            ],
            [
                'cash_register_id' => $register?->getKey(),
                'opening_float' => 150000,
                'expected_balance' => 150000,
                'total_cash' => 0,
                'total_mobile_money' => 0,
                'status' => CashSessionStatus::Open,
                'opened_at' => Carbon::parse($today.' 07:30:00'),
            ],
        );

        $cashLedger = $this->ensureLedger($session, $agent, CashLedgerKind::Cash, 'cash', null, 150000);
        $accounts = AgencyPaymentAccount::query()->where('agency_id', $agency->getKey())->where('status', 'active')->get();

        foreach ($accounts as $account) {
            $this->ensureLedger($session, $agent, CashLedgerKind::MobileMoney, $account->uuid, $account, 250000);
        }

        // Ventes du matin
        $this->createMovement($session, $cashLedger, $agent, 'counter_sale_cash', CashMovementDirection::In, CashMovementStatus::Posted, 35000, 'TKT-CTN-0101', 'Billet Cotonou-Parakou (Espèces)');
        $this->createMovement($session, $cashLedger, $agent, 'counter_sale_cash', CashMovementDirection::In, CashMovementStatus::Posted, 15000, 'TKT-CTN-0102', 'Billet Cotonou-Bohicon (Espèces)');

        if ($accounts->isNotEmpty()) {
            $momoLedger = $session->ledgers()->where('agency_payment_account_id', $accounts->first()->getKey())->first();
            if ($momoLedger) {
                $this->createMovement($session, $momoLedger, $agent, 'counter_sale_mobile_money', CashMovementDirection::In, CashMovementStatus::Posted, 45000, 'MM-TX-9801', 'Paiement Mobile Money 3 places');
            }
        }

        // Reversement en attente (pending) avec preuve pour tester l'action du drawer
        $this->createMovement($session, $cashLedger, $agent, 'reversement', CashMovementDirection::Out, CashMovementStatus::Pending, 50000, 'REV-2026-0819', 'Reversement vers coffre fort agence', destination: 'Coffre central CTN');
    }

    private function seedYesterdayClosedSession(Agency $agency, User $agent, ?CashRegister $register): void
    {
        $date = now()->subDay()->toDateString();
        $session = CashSession::query()->firstOrCreate(
            [
                'agent_id' => $agent->getKey(),
                'agency_id' => $agency->getKey(),
                'business_date' => $date,
            ],
            [
                'cash_register_id' => $register?->getKey(),
                'opening_float' => 150000,
                'expected_balance' => 240000,
                'total_cash' => 240000,
                'total_mobile_money' => 0,
                'status' => CashSessionStatus::Closed,
                'opened_at' => Carbon::parse($date.' 07:00:00'),
                'submitted_at' => Carbon::parse($date.' 18:30:00'),
                'closed_at' => Carbon::parse($date.' 18:35:00'),
            ],
        );

        $cashLedger = $this->ensureLedger($session, $agent, CashLedgerKind::Cash, 'cash', null, 150000, 240000, 240000, 0, CashLedgerStatus::Closed);
        $this->createMovement($session, $cashLedger, $agent, 'counter_sale_cash', CashMovementDirection::In, CashMovementStatus::Posted, 90000, 'TKT-CTN-0091', 'Vente 6 billets réguliers');
    }

    private function seedPendingApprovalSession(Agency $agency, User $agent, ?CashRegister $register): void
    {
        $date = now()->subDays(2)->toDateString();
        $session = CashSession::query()->firstOrCreate(
            [
                'agent_id' => $agent->getKey(),
                'agency_id' => $agency->getKey(),
                'business_date' => $date,
            ],
            [
                'cash_register_id' => $register?->getKey(),
                'opening_float' => 100000,
                'expected_balance' => 220000,
                'total_cash' => 205000,
                'status' => CashSessionStatus::PendingApproval,
                'opened_at' => Carbon::parse($date.' 07:15:00'),
                'submitted_at' => Carbon::parse($date.' 19:00:00'),
                'closing_comment' => 'Écart de -15 000 XOF constaté au comptage du soir (erreur rendu monnaie probable).',
            ],
        );

        $cashLedger = $this->ensureLedger($session, $agent, CashLedgerKind::Cash, 'cash', null, 100000, 220000, 205000, -15000, CashLedgerStatus::PendingApproval);
        $this->createMovement($session, $cashLedger, $agent, 'counter_sale_cash', CashMovementDirection::In, CashMovementStatus::Posted, 120000, 'TKT-CTN-0080', 'Guichet 2 ventes groupe');
    }

    private function seedApprovedVarianceSession(Agency $agency, User $agent, User $manager, ?CashRegister $register): void
    {
        $date = now()->subDays(3)->toDateString();
        $session = CashSession::query()->firstOrCreate(
            [
                'agent_id' => $agent->getKey(),
                'agency_id' => $agency->getKey(),
                'business_date' => $date,
            ],
            [
                'cash_register_id' => $register?->getKey(),
                'opening_float' => 100000,
                'expected_balance' => 180000,
                'total_cash' => 175000,
                'status' => CashSessionStatus::Closed,
                'opened_at' => Carbon::parse($date.' 07:30:00'),
                'submitted_at' => Carbon::parse($date.' 18:00:00'),
                'closed_at' => Carbon::parse($date.' 18:10:00'),
                'approved_at' => Carbon::parse($date.' 18:20:00'),
                'approved_by' => $manager->getKey(),
                'closing_comment' => 'Différence de -5000 XOF : billet faux retiré de la circulation.',
            ],
        );

        $cashLedger = $this->ensureLedger($session, $agent, CashLedgerKind::Cash, 'cash', null, 100000, 180000, 175000, -5000, CashLedgerStatus::Closed);
        $this->createMovement($session, $cashLedger, $agent, 'counter_sale_cash', CashMovementDirection::In, CashMovementStatus::Posted, 80000, 'TKT-CTN-0070', 'Ventes guichet matin');
    }

    private function seedStaleOpenSession(Agency $agency, User $agent, ?CashRegister $register): void
    {
        $date = now()->subDays(4)->toDateString();

        CashSession::query()
            ->where('agent_id', $agent->getKey())
            ->where('status', CashSessionStatus::Open->value)
            ->whereDate('business_date', '!=', $date)
            ->update([
                'status' => CashSessionStatus::Closed->value,
                'closed_at' => now()->subDays(3),
            ]);

        $session = CashSession::query()->firstOrCreate(
            [
                'agent_id' => $agent->getKey(),
                'agency_id' => $agency->getKey(),
                'business_date' => $date,
            ],
            [
                'cash_register_id' => $register?->getKey(),
                'opening_float' => 100000,
                'expected_balance' => 140000,
                'status' => CashSessionStatus::Open,
                'opened_at' => Carbon::parse($date.' 08:00:00'),
            ],
        );

        $cashLedger = $this->ensureLedger($session, $agent, CashLedgerKind::Cash, 'cash', null, 100000);
        $this->createMovement($session, $cashLedger, $agent, 'counter_sale_cash', CashMovementDirection::In, CashMovementStatus::Posted, 40000, 'TKT-CTN-0055', 'Ventes express');
    }

    private function seedForceClosedSession(Agency $agency, User $agent, User $manager, ?CashRegister $register): void
    {
        $date = now()->subDays(5)->toDateString();
        $session = CashSession::query()->firstOrCreate(
            [
                'agent_id' => $agent->getKey(),
                'agency_id' => $agency->getKey(),
                'business_date' => $date,
            ],
            [
                'cash_register_id' => $register?->getKey(),
                'opening_float' => 100000,
                'expected_balance' => 100000,
                'status' => CashSessionStatus::Closed,
                'opened_at' => Carbon::parse($date.' 07:00:00'),
                'force_closed_at' => Carbon::parse($date.' 20:00:00'),
                'force_closed_by' => $manager->getKey(),
                'closing_comment' => 'Agent indisponible pour clôture physique en fin de service.',
            ],
        );

        $this->ensureLedger($session, $agent, CashLedgerKind::Cash, 'cash', null, 100000, 100000, null, null, CashLedgerStatus::Closed);
    }

    private function ensureLedger(
        CashSession $session,
        User $agent,
        CashLedgerKind $kind,
        string $ledgerKey,
        ?AgencyPaymentAccount $account,
        int $openingBalance,
        int $expectedBalance = 0,
        ?int $actualBalance = null,
        ?int $variance = null,
        CashLedgerStatus $status = CashLedgerStatus::Open,
    ): CashLedger {
        $expected = $expectedBalance > 0 ? $expectedBalance : $openingBalance;
        $ledger = CashLedger::query()->firstOrCreate(
            [
                'cash_session_id' => $session->getKey(),
                'ledger_key' => $ledgerKey,
            ],
            [
                'kind' => $kind,
                'agency_payment_account_id' => $account?->getKey(),
                'opening_balance' => $openingBalance,
                'expected_balance' => $expected,
                'actual_balance' => $actualBalance,
                'variance' => $variance,
                'status' => $status,
                'opened_by' => $agent->getKey(),
                'opened_at' => $session->opened_at ?? now(),
            ],
        );

        if (! $ledger->hasMedia('opening-evidence')) {
            $ledger->addMediaFromString($this->demoPng())
                ->usingFileName('preuve-ouverture-'.$session->business_date->toDateString().'.png')
                ->withCustomProperties(['evidence_type' => 'opening_balance', 'seeded' => true])
                ->toMediaCollection('opening-evidence');
        }

        if ($status === CashLedgerStatus::Closed && ! $ledger->hasMedia('closing-evidence')) {
            $ledger->addMediaFromString($this->demoPng())
                ->usingFileName('preuve-cloture-'.$session->business_date->toDateString().'.png')
                ->withCustomProperties(['evidence_type' => 'closing_balance', 'seeded' => true])
                ->toMediaCollection('closing-evidence');
        }

        return $ledger;
    }

    private function createMovement(
        CashSession $session,
        CashLedger $ledger,
        User $agent,
        string $type,
        CashMovementDirection $direction,
        CashMovementStatus $status,
        int $amount,
        string $reference,
        string $description = '',
        ?string $destination = null,
    ): void {
        if (CashMovement::query()->where('cash_session_id', $session->getKey())->where('reference', $reference)->exists()) {
            return;
        }

        CashMovement::query()->create([
            'cash_session_id' => $session->getKey(),
            'cash_ledger_id' => $ledger->getKey(),
            'type' => $type,
            'direction' => $direction,
            'status' => $status,
            'amount' => $amount,
            'currency' => 'XOF',
            'description' => $description,
            'reference' => $reference,
            'destination_label' => $destination,
            'created_by' => $agent->getKey(),
            'occurred_at' => $session->opened_at?->copy()->addMinutes(45) ?? now(),
        ]);

        if ($status === CashMovementStatus::Posted || $status === CashMovementStatus::Approved) {
            $delta = $direction === CashMovementDirection::In ? $amount : -$amount;
            $ledger->increment('expected_balance', $delta);
        }
    }

    private function demoPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true) ?: '';
    }
}
