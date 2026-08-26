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
use App\Modules\Cash\Models\CashSession;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Database\Seeder;

class KoraCashDemoSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::query()->where('code', 'CTN')->firstOrFail();
        $agent = User::query()->where('email', 'agent.sandrine@kora.demo')->firstOrFail();
        $agent->update(['agency_id' => $agency->getKey()]);

        $session = CashSession::query()
            ->where('agent_id', $agent->getKey())
            ->where('agency_id', $agency->getKey())
            ->whereDate('business_date', now()->toDateString())
            ->first();

        if ($session === null) {
            $session = CashSession::query()->create([
                'agent_id' => $agent->getKey(),
                'agency_id' => $agency->getKey(),
                'business_date' => now()->toDateString(),
                'opening_float' => 150000,
                'expected_balance' => 150000,
                'total_cash' => 0,
                'total_mobile_money' => 0,
                'status' => CashSessionStatus::Open,
                'opened_at' => now(),
            ]);
        }

        $this->seedLedger($session, $agent, CashLedgerKind::Cash, 'cash', null, 150000);

        AgencyPaymentAccount::query()
            ->where('agency_id', $agency->getKey())
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->each(function (AgencyPaymentAccount $account) use ($session, $agent): void {
                $this->seedLedger($session, $agent, CashLedgerKind::MobileMoney, $account->uuid, $account, 300000);
            });

        $this->seedMovement($session, $agent, 'cash', 25000, 'DEMO-CASH-001');
        $this->seedMovement($session, $agent, AgencyPaymentAccount::query()
            ->where('agency_id', $agency->getKey())
            ->where('network', 'mtn_momo')
            ->value('uuid'), 40000, 'DEMO-MTN-001');
    }

    private function seedLedger(
        CashSession $session,
        User $agent,
        CashLedgerKind $kind,
        string $ledgerKey,
        ?AgencyPaymentAccount $account,
        int $openingBalance,
    ): CashLedger {
        $ledger = CashLedger::query()->firstOrCreate(
            [
                'cash_session_id' => $session->getKey(),
                'ledger_key' => $ledgerKey,
            ],
            [
                'kind' => $kind,
                'agency_payment_account_id' => $account?->getKey(),
                'opening_balance' => $openingBalance,
                'expected_balance' => $openingBalance,
                'status' => CashLedgerStatus::Open,
                'opened_by' => $agent->getKey(),
                'opened_at' => now(),
            ],
        );

        if (! $ledger->hasMedia('opening-evidence')) {
            $ledger->addMediaFromString($this->demoPng())
                ->usingFileName('demo-opening.png')
                ->withCustomProperties([
                    'evidence_type' => 'opening_balance',
                    'seeded' => true,
                ])
                ->toMediaCollection('opening-evidence');
        }

        return $ledger;
    }

    private function seedMovement(CashSession $session, User $agent, ?string $ledgerKey, int $amount, string $reference): void
    {
        if ($ledgerKey === null) {
            return;
        }

        $ledger = $session->ledgers()->where('ledger_key', $ledgerKey)->first();
        if ($ledger === null || CashMovement::query()->where('cash_ledger_id', $ledger->getKey())->where('reference', $reference)->exists()) {
            return;
        }

        CashMovement::query()->create([
            'cash_session_id' => $session->getKey(),
            'cash_ledger_id' => $ledger->getKey(),
            'type' => 'counter_sale_'.$ledger->kind->value,
            'direction' => CashMovementDirection::In,
            'status' => CashMovementStatus::Posted,
            'amount' => $amount,
            'currency' => 'XOF',
            'description' => 'Mouvement de démonstration',
            'reference' => $reference,
            'created_by' => $agent->getKey(),
            'occurred_at' => now(),
        ]);

        $ledger->increment('expected_balance', $amount);
    }

    private function demoPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true) ?: '';
    }
}
