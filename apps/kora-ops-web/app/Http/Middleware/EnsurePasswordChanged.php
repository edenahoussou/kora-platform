<?php

namespace App\Http\Middleware;

use App\Modules\IdentityAccess\Services\KoraPointWebAccessGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function __construct(private readonly KoraPointWebAccessGuard $koraPointWebAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user?->must_change_password
            && $user->isKoraPointUser()
            && ! $this->koraPointWebAccess->canUseTemporaryPassword($user)
        ) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => $this->koraPointWebAccess->unavailableMessage(),
            ]);
        }

        if ($user?->must_change_password && ! $request->routeIs([
            'security.edit',
            'profile.edit',
            'user-password.update',
            'password.confirm',
            'password.confirm.store',
            'logout',
        ])) {
            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
