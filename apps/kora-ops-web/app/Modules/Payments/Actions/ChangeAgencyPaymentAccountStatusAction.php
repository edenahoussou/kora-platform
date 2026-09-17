<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeAgencyPaymentAccountStatusAction
{
    public function execute(AgencyPaymentAccount $account, string $status, ?string $replacementUuid = null): AgencyPaymentAccount
    {
        return DB::transaction(function () use ($account, $status, $replacementUuid): AgencyPaymentAccount {
            Agency::query()->whereKey($account->agency_id)->lockForUpdate()->firstOrFail();
            $account = AgencyPaymentAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();

            if ($status === 'inactive') {
                $this->assertNoOpenSession($account);
                $account->update(['status' => 'inactive']);

                return $account->refresh();
            }

            if ($account->isActive()) {
                return $account->refresh();
            }

            $activeAccount = AgencyPaymentAccount::query()
                ->where('agency_id', $account->agency_id)
                ->where('network', $account->getRawOriginal('network'))
                ->active()
                ->lockForUpdate()
                ->first();

            if ($activeAccount !== null) {
                if ($replacementUuid !== $activeAccount->uuid) {
                    throw ValidationException::withMessages([
                        'replace_account_uuid' => 'Confirmez le changement vers ce compte Mobile Money.',
                    ]);
                }

                $this->assertNoOpenSession($activeAccount);
                $activeAccount->update(['status' => 'inactive']);
            } elseif ($replacementUuid !== null) {
                throw ValidationException::withMessages([
                    'replace_account_uuid' => 'Le compte Mobile Money actif a déjà changé.',
                ]);
            }

            $account->update(['status' => 'active']);

            return $account->refresh();
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
                'account' => 'Clôturez la session de caisse utilisant ce compte avant de modifier son statut.',
            ]);
        }
    }
}
