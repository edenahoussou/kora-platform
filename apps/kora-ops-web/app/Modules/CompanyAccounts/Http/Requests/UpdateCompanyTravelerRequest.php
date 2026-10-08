<?php

namespace App\Modules\CompanyAccounts\Http\Requests;

/**
 * Mêmes règles que la création : la règle d'unicité ignore le voyageur
 * courant lorsque la route le fournit (mise à jour complète via PUT).
 */
class UpdateCompanyTravelerRequest extends StoreCompanyTravelerRequest
{
    //
}
