<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestrictPartnerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? ($request->is('api/*') ? Auth::guard('sanctum')->user() : null);
        if ($user instanceof User && $user->isKoraPointUser()) {
            if ($request->is('api/*')) {
                abort_unless($request->bearerToken() === null && $user->status === UserStatus::Active && $request->routeIs('api.v1.notifications.*'), 403);

                return $next($request);
            }
            $allowed = $request->routeIs('kora-points', 'kora-points.*', 'commissions', 'settlements', 'logout', 'profile.*', 'security.*', 'user-password.*', 'two-factor.*', 'passkeys.*', 'password.*', 'verification.*', 'notifications.*', 'settings.notifications', 'settings.notifications.*');
            if ($user->hasRole('kora_point_agent')) {
                $allowed = $allowed || $request->routeIs('sale', 'sale.*', 'cash', 'cash.*', 'tickets', 'tickets.*', 'dashboard');
            }
            abort_unless($allowed, 403);
        }

        return $next($request);
    }
}
