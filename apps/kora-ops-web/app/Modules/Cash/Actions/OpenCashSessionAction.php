<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashLedgerStatus;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OpenCashSessionAction
{
    /**
     * @param  list<array{kind: string, account_uuid: string|null, opening_balance: int, opening_comment?: string|null, evidence: UploadedFile}>  $ledgers
     */
    public function execute(User $agent, array $ledgers): CashSession
    {
        if ($agent->agency_id === null) {
            throw ValidationException::withMessages([
                'cash_session' => 'Votre profil n’est rattaché à aucune agence.',
            ]);
        }

        return DB::transaction(function () use ($agent, $ledgers): CashSession {
            $existing = CashSession::query()
                ->where('agent_id', $agent->getKey())
                ->where('agency_id', $agent->agency_id)
                ->whereDate('business_date', now()->toDateString())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Une caisse existe déjà pour cet agent et cette date.',
                ]);
            }

            $accounts = AgencyPaymentAccount::query()
                ->where('agency_id', $agent->agency_id)
                ->where('status', 'active')
                ->orderBy('id')
                ->get();

            $submittedLedgers = collect($ledgers)->mapWithKeys(function (array $ledger): array {
                $kind = (string) $ledger['kind'];
                $accountUuid = $ledger['account_uuid'] ?? null;

                return [$kind.':'.($accountUuid ?: 'cash') => $ledger];
            });
            $expectedKeys = collect(['cash:cash'])
                ->merge($accounts->map(fn (AgencyPaymentAccount $account): string => 'mobile_money:'.$account->uuid))
                ->sort()
                ->values();

            if ($submittedLedgers->keys()->sort()->values()->all() !== $expectedKeys->all()) {
                throw ValidationException::withMessages([
                    'ledgers' => 'Déclarez un solde et une preuve pour chaque compte actif de l’agence.',
                ]);
            }

            $cashLedger = $submittedLedgers->get('cash:cash');
            $session = CashSession::query()->create([
                'agent_id' => $agent->getKey(),
                'agency_id' => $agent->agency_id,
                'business_date' => now()->toDateString(),
                'opening_float' => (int) $cashLedger['opening_balance'],
                'expected_balance' => (int) $cashLedger['opening_balance'],
                'total_cash' => 0,
                'total_mobile_money' => 0,
                'status' => CashSessionStatus::Open,
                'opened_at' => now(),
            ]);

            $this->createLedger($session, $agent, $cashLedger, CashLedgerKind::Cash, 'cash', null);

            foreach ($accounts as $account) {
                $ledger = $submittedLedgers->get('mobile_money:'.$account->uuid);
                $this->createLedger($session, $agent, $ledger, CashLedgerKind::MobileMoney, $account->uuid, $account);
            }

            activity('cash')
                ->performedOn($session)
                ->causedBy($agent)
                ->event('session_opened')
                ->withProperties([
                    'cash_session_uuid' => $session->uuid,
                    'ledger_count' => $session->ledgers()->count(),
                ])
                ->log('Session de caisse ouverte');

            return $session->load('ledgers.agencyPaymentAccount');
        });
    }

    /**
     * @param  array{kind: string, account_uuid: string|null, opening_balance: int, opening_comment?: string|null, evidence: UploadedFile}  $attributes
     */
    private function createLedger(
        CashSession $session,
        User $agent,
        array $attributes,
        CashLedgerKind $kind,
        string $ledgerKey,
        ?AgencyPaymentAccount $account,
    ): CashLedger {
        $ledger = CashLedger::query()->create([
            'cash_session_id' => $session->getKey(),
            'ledger_key' => $ledgerKey,
            'kind' => $kind,
            'agency_payment_account_id' => $account?->getKey(),
            'opening_balance' => (int) $attributes['opening_balance'],
            'expected_balance' => (int) $attributes['opening_balance'],
            'status' => CashLedgerStatus::Open,
            'opened_by' => $agent->getKey(),
            'opened_at' => now(),
            'opening_comment' => $attributes['opening_comment'] ?? null,
        ]);

        $ledger->addMedia($attributes['evidence'])
            ->withCustomProperties([
                'evidence_type' => 'opening_balance',
                'ledger_uuid' => $ledger->uuid,
                'captured_by' => $agent->uuid,
                'captured_at' => now()->toISOString(),
            ])
            ->toMediaCollection('opening-evidence');

        return $ledger;
    }
}
