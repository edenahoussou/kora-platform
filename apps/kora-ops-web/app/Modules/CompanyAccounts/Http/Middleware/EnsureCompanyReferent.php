<?php

namespace App\Modules\CompanyAccounts\Http\Middleware;

use App\Modules\CompanyAccounts\Actions\ResolveReferentCompanyAccountAction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint l'espace entreprise au référent actif d'un compte organisationnel
 * actif. Le compte résolu est mis à disposition de la requête.
 */
class EnsureCompanyReferent
{
    public function __construct(
        private readonly ResolveReferentCompanyAccountAction $resolveReferentAccount,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = $this->resolveReferentAccount->handle($request->user());

        if ($account === null) {
            abort(403);
        }

        $request->attributes->set('companyAccount', $account);

        return $next($request);
    }
}
