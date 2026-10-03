<?php

namespace App\Modules\CompanyAccounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyPortalController extends Controller
{
    public function __construct(private readonly CompanyCreditService $companyCredit) {}

    public function index(Request $request): Response
    {
        /** @var CompanyAccount $account */
        $account = $request->attributes->get('companyAccount');
        $periodKey = now()->format('Y-m');

        return Inertia::render('company-portal/index', [
            'account' => [
                'uuid' => $account->uuid,
                'name' => $account->name,
                'reference' => $account->reference,
                'type' => $account->type->value,
            ],
            'credit' => [
                'monthlyLimit' => (int) $account->monthly_credit_limit,
                'available' => $this->companyCredit->availableCredit($account, $periodKey),
                'periodKey' => $periodKey,
            ],
        ]);
    }
}
