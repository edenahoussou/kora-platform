<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Services\KoraPointPartnerSessionGuard;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RestrictPartnerAccess
{
    public function __construct(private readonly KoraPointPartnerSessionGuard $partnerSession) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? ($request->is('api/*') ? Auth::guard('sanctum')->user() : null);
        if ($user instanceof User && $user->isKoraPointUser()) {
            if ($request->is('api/*')) {
                abort_unless($request->bearerToken() === null && $user->status === UserStatus::Active && $request->routeIs('api.v1.notifications.*'), 403);

                return $next($request);
            }

            $blockedMessage = $this->partnerSession->blockedMessage($user);
            if ($blockedMessage !== null) {
                return $this->partnerSession->terminate($request, $blockedMessage);
            }

            $isOrganizationPartner = $user->hasAnyRole(['kora_point_supervisor', 'kora_point_treasurer']);
            $commonAllowed = $request->routeIs('logout', 'profile.*', 'security.*', 'user-password.*', 'two-factor.*', 'passkeys.*', 'password.*', 'verification.*', 'notifications.*', 'settings.notifications', 'settings.notifications.*');

            if ($isOrganizationPartner) {
                $allowed = $commonAllowed
                    || $request->routeIs('kora-partner.*', 'kora-points.documents.download', 'kora-points.organizations.documents', 'kora-points.remittances.*');

                if ($user->hasRole('kora_point_supervisor')) {
                    $allowed = $allowed || $request->routeIs('kora-points.store', 'kora-points.update', 'kora-points.status');
                }

                if (! $allowed) {
                    if ((bool) $request->session()->get('kora_point_partner_initial_landing', false)) {
                        return $this->partnerSession->terminate($request, $this->partnerSession->unrecognizedSpaceMessage());
                    }

                    abort(403);
                }

                $isInitialLanding = $request->routeIs('kora-partner.dashboard')
                    && (bool) $request->session()->get('kora_point_partner_initial_landing', false);

                try {
                    $response = $next($request);
                } catch (Throwable $exception) {
                    $isForbidden = $exception instanceof AuthorizationException
                        || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403);

                    if ($isInitialLanding && $isForbidden) {
                        return $this->partnerSession->terminate($request, $this->partnerSession->unrecognizedSpaceMessage());
                    }

                    throw $exception;
                }

                if ($isInitialLanding) {
                    if ($response->getStatusCode() === 403) {
                        return $this->partnerSession->terminate($request, $this->partnerSession->unrecognizedSpaceMessage());
                    }

                    if ($response->isSuccessful()) {
                        $request->session()->forget('kora_point_partner_initial_landing');
                    }
                }

                return $response;
            }

            $allowed = $commonAllowed || $request->routeIs('kora-points', 'kora-points.*', 'kora-point.*', 'commissions', 'settlements');
            if ($user->hasRole('kora_point_agent')) {
                $allowed = $allowed || $request->routeIs('sale', 'sale.*', 'cash', 'cash.*', 'tickets', 'tickets.*', 'dashboard');
            }
            abort_unless($allowed, 403);
        }

        return $next($request);
    }
}
