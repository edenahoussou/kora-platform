<?php

namespace App\Http\Responses;

use App\Modules\CompanyAccounts\Actions\ResolveReferentCompanyAccountAction;
use App\Modules\IdentityAccess\Services\KoraPointWebAccessGuard;
use App\Modules\KoraPoints\Services\KoraPointPartnerSessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function __construct(
        private readonly ResolveReferentCompanyAccountAction $referentAccount,
        private readonly KoraPointWebAccessGuard $koraPointWebAccess,
        private readonly KoraPointPartnerSessionGuard $koraPointPartnerSession,
    ) {}

    /** @param Request $request */
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($user?->hasAnyRole(['kora_point_supervisor', 'kora_point_treasurer'])) {
            $blockedMessage = $this->koraPointPartnerSession->blockedMessage($user);

            if ($blockedMessage !== null) {
                return $this->koraPointPartnerSession->terminate($request, $blockedMessage);
            }
        }

        if (
            $user?->must_change_password
            && ! $this->koraPointWebAccess->canUseTemporaryPassword($user)
        ) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => $this->koraPointWebAccess->unavailableMessage(),
            ]);
        }

        if ($user?->isKoraPointUser()) {
            if ($user->hasAnyRole(['kora_point_supervisor', 'kora_point_treasurer'])) {
                $request->session()->put('kora_point_partner_initial_landing', true);
            }

            if ($user->must_change_password) {
                return redirect()->route('security.edit');
            }

            if ($user->hasRole('kora_point_agent')) {
                return redirect()->route('kora-point.dashboard');
            }

            if ($user->hasAnyRole(['kora_point_supervisor', 'kora_point_treasurer'])) {
                return redirect()->route('kora-partner.dashboard');
            }

            return $this->koraPointPartnerSession->terminate($request, $this->koraPointPartnerSession->unrecognizedSpaceMessage());
        }

        if ($this->referentAccount->isPortalOnlyUser($user)) {
            return redirect()->intended(route('company-portal.index'));
        }

        return redirect()->intended(config('fortify.home'));
    }
}
