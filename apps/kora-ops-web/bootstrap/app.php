<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\IdentityAccess\Http\Middleware\EnsureOpsSession;
use App\Modules\IdentityAccess\Http\Middleware\EnsureTerminalIsUsable;
use App\Modules\IdentityAccess\Services\OpsSessionAuthenticationFailureResponder;
use App\Modules\Notifications\Http\Middleware\EnsureNotificationsAccess;
use App\Modules\Settings\Middleware\ApplyKoraSettings;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => 'api/v1', 'middleware' => ['auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Trust the Nginx reverse proxy on the VPS so Laravel generates
        // HTTPS URLs when behind the Let's Encrypt terminating proxy.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            ApplyKoraSettings::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            EnsurePasswordChanged::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [ApplyKoraSettings::class]);

        $middleware->alias([
            'terminal.active' => EnsureTerminalIsUsable::class,
            'ops.session' => EnsureOpsSession::class,
            'notifications.access' => EnsureNotificationsAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Routes OPS Mobile : un état d'authentification déterministe
        // (session_expired / session_revoked / session_invalid) à la place du
        // 401 générique. Retourne null ailleurs : rendu Laravel par défaut.
        $exceptions->render(
            fn (AuthenticationException $exception, Request $request) => app(OpsSessionAuthenticationFailureResponder::class)->respond($request),
        );
    })->create();
