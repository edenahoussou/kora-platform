<?php

namespace App\Http\Responses;

use App\Modules\CompanyAccounts\Actions\ResolveReferentCompanyAccountAction;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function __construct(
        private readonly ResolveReferentCompanyAccountAction $referentAccount,
    ) {}

    public function toResponse($request): Response
    {
        if ($this->referentAccount->isPortalOnlyUser($request->user())) {
            return redirect()->intended(route('company-portal.index'));
        }

        return redirect()->intended(config('fortify.home'));
    }
}
