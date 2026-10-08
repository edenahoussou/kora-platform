<?php

namespace App\Modules\CompanyAccounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\CompanyAccounts\Data\CompanyTravelerStatus;
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
            'stats' => [
                'travelersTotal' => $account->travelers()->count(),
                'travelersActive' => $account->travelers()
                    ->where('status', CompanyTravelerStatus::Active->value)
                    ->count(),
                'monthlyReservations' => $account->reservations()
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->whereNotIn('status', [
                        ReservationStatus::Cancelled->value,
                        ReservationStatus::Expired->value,
                        ReservationStatus::Unavailable->value,
                    ])
                    ->count(),
            ],
        ]);
    }
}
