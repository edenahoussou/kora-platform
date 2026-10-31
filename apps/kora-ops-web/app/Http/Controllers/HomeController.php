<?php

namespace App\Http\Controllers;

use App\Modules\CompanyAccounts\Actions\ResolveReferentCompanyAccountAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(
        private readonly ResolveReferentCompanyAccountAction $referentAccount,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->isKoraPointUser() && ! $user->hasRole('kora_point_agent')) {
            return redirect()->route('kora-points');
        }

        return redirect()->route(
            $this->referentAccount->isPortalOnlyUser($user)
                ? 'company-portal.index'
                : 'dashboard',
        );
    }
}
