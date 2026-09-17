<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAgencyPaymentAccountAction
{
    /**
     * @param  array{agency: Agency, network: MobileMoneyNetwork, label: string, account_identifier: string, replace_account_uuid: string|null}  $attributes
     */
    public function execute(array $attributes): AgencyPaymentAccount
    {
        return DB::transaction(function () use ($attributes): AgencyPaymentAccount {
            $agency = Agency::query()
                ->whereKey($attributes['agency']->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $network = $attributes['network']->value;
            $activeAccount = AgencyPaymentAccount::query()
                ->where('agency_id', $agency->getKey())
                ->where('network', $network)
                ->active()
                ->lockForUpdate()
                ->first();
            $replacementUuid = $attributes['replace_account_uuid'];

            if ($activeAccount !== null && $replacementUuid !== $activeAccount->uuid) {
                throw ValidationException::withMessages([
                    'replace_account_uuid' => 'Confirmez le remplacement du compte Mobile Money actif pour ce réseau.',
                ]);
            }

            if ($activeAccount === null && $replacementUuid !== null) {
                throw ValidationException::withMessages([
                    'replace_account_uuid' => 'Le compte Mobile Money à remplacer n’est plus actif.',
                ]);
            }

            if ($activeAccount !== null) {
                $this->assertNoOpenSession($activeAccount);
                $activeAccount->update(['status' => 'inactive']);
            }

            return AgencyPaymentAccount::query()->create([
                'agency_id' => $agency->getKey(),
                'network' => $attributes['network'],
                'label' => $attributes['label'],
                'account_identifier' => $attributes['account_identifier'],
                'status' => 'active',
            ]);
        });
    }

    private function assertNoOpenSession(AgencyPaymentAccount $account): void
    {
        $hasOpenSession = CashLedger::query()
            ->where('agency_payment_account_id', $account->getKey())
            ->whereHas('cashSession', fn ($query) => $query->where('status', CashSessionStatus::Open->value))
            ->exists();

        if ($hasOpenSession) {
            throw ValidationException::withMessages([
                'account' => 'Clôturez la session de caisse utilisant ce compte avant de le remplacer.',
            ]);
        }
    }
}
