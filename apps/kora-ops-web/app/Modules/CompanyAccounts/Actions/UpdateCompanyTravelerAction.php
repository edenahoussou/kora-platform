<?php

namespace App\Modules\CompanyAccounts\Actions;

use App\Modules\CompanyAccounts\Models\CompanyTraveler;

class UpdateCompanyTravelerAction
{
    /**
     * @param  array{first_name?: string, last_name?: string, phone?: string, email?: string|null, document_type?: string|null, document_number?: string|null, nationality?: string|null, birth_date?: string|null, yellow_fever_vaccinated?: bool|null}  $data
     */
    public function execute(CompanyTraveler $traveler, array $data): CompanyTraveler
    {
        $traveler->update($data);

        return $traveler->refresh();
    }
}
