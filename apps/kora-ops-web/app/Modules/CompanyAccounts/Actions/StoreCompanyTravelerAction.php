<?php

namespace App\Modules\CompanyAccounts\Actions;

use App\Modules\CompanyAccounts\Data\CompanyTravelerStatus;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyTraveler;

class StoreCompanyTravelerAction
{
    /**
     * @param  array{first_name: string, last_name: string, phone: string, email?: string|null, document_type?: string|null, document_number?: string|null, nationality?: string|null, birth_date?: string|null, yellow_fever_vaccinated?: bool|null}  $data
     */
    public function execute(CompanyAccount $account, array $data): CompanyTraveler
    {
        /** @var CompanyTraveler $traveler */
        $traveler = $account->travelers()->create([
            ...$data,
            'status' => CompanyTravelerStatus::Active,
        ]);

        return $traveler;
    }
}
