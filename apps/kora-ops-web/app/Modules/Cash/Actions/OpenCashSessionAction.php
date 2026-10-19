<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashLedgerStatus;
use App\Modules\Cash\Data\CashRegisterKind;
use App\Modules\Cash\Data\CashRegisterStatus;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OpenCashSessionAction
{
    /**
     * @param  array{cash_register_uuid: string|null, ledgers: list<array{kind: string, account_uuid: string|null, opening_balance: int, opening_comment?: string|null, evidence: UploadedFile, evidence_captured_at?: string}>}  $attributes
     */
    public function execute(User $agent, array $attributes): CashSession
    {
        if ($agent->agency_id === null) {
            throw ValidationException::withMessages([
                'cash_session' => 'Votre profil n’est rattaché à aucune agence.',
            ]);
        }

        return DB::transaction(function () use ($agent, $attributes): CashSession {
            $ledgers = $attributes['ledgers'];
            $register = $this->resolveRegister($agent, $attributes['cash_register_uuid']);

            $existingForAgent = CashSession::query()
                ->where('agent_id', $agent->getKey())
                ->where('agency_id', $agent->agency_id)
                ->where('status', CashSessionStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if ($existingForAgent !== null) {
                $message = $existingForAgent->business_date->isToday()
                    ? 'Cet agent possède déjà une session de caisse ouverte.'
                    : 'Une session de caisse du '.$existingForAgent->business_date->format('d/m/Y').' est encore ouverte. Clôturez-la avant d’en ouvrir une nouvelle.';

                throw ValidationException::withMessages([
                    'cash_session' => $message,
                ]);
            }

            $existingForRegister = CashSession::query()
                ->where('cash_register_id', $register->getKey())
                ->where('status', CashSessionStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if ($existingForRegister !== null) {
                throw ValidationException::withMessages([
                    'cash_register_uuid' => 'Cette caisse possède déjà une session ouverte.',
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
                'cash_register_id' => $register->getKey(),
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
                    'cash_register_uuid' => $register->uuid,
                ])
                ->log('Session de caisse ouverte');

            return $session->load('ledgers.agencyPaymentAccount');
        });
    }

    private function resolveRegister(User $agent, ?string $registerUuid): CashRegister
    {
        $query = CashRegister::query()
            ->where('agency_id', $agent->agency_id)
            ->where('kind', CashRegisterKind::Counter->value)
            ->where('status', CashRegisterStatus::Active->value);

        if ($registerUuid !== null && $registerUuid !== '') {
            $register = (clone $query)->where('uuid', $registerUuid)->first();
        } else {
            $register = (clone $query)
                ->whereHas('authorizedUsers', fn ($users) => $users->whereKey($agent->getKey()))
                ->orderBy('id')
                ->first() ?? (clone $query)->orderBy('id')->first();
        }

        if (! $register instanceof CashRegister && ($registerUuid === null || $registerUuid === '')) {
            $register = CashRegister::query()->firstOrCreate(
                [
                    'agency_id' => $agent->agency_id,
                    'code' => 'LEGACY-'.$agent->getKey(),
                ],
                [
                    'label' => 'Caisse historique de '.$agent->name,
                    'kind' => CashRegisterKind::Counter,
                    'status' => CashRegisterStatus::Active,
                ],
            );
        }

        if (! $register instanceof CashRegister) {
            throw ValidationException::withMessages([
                'cash_register_uuid' => 'Aucune caisse guichet active n’est disponible pour cette agence.',
            ]);
        }

        if ($register->authorizedUsers()->exists() && ! $register->authorizedUsers()->whereKey($agent->getKey())->exists()) {
            throw ValidationException::withMessages([
                'cash_register_uuid' => 'Vous n’êtes pas autorisé à ouvrir cette caisse.',
            ]);
        }

        return $register;
    }

    /**
     * @param  array{kind: string, account_uuid: string|null, opening_balance: int, opening_comment?: string|null, evidence: UploadedFile, evidence_captured_at?: string}  $attributes
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
                'evidence_source' => 'live_camera',
                'captured_by' => $agent->uuid,
                'captured_at' => now()->toISOString(),
                'captured_at_client' => $attributes['evidence_captured_at'] ?? null,
            ])
            ->toMediaCollection('opening-evidence');

        return $ledger;
    }
}
