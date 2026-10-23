<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

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
