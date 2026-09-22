<?php

namespace App\Http\Middleware;

use App\Modules\BusNetwork\Models\MapSetting;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Settings\Settings\IntegrationsSettings;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $settings = app(IntegrationsSettings::class);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => null,
                    'canViewCash' => $user->can('viewAny', CashSession::class),
                    'canViewTickets' => $user->can('viewAny', Ticket::class),
                    'email_verified_at' => $user->email_verified_at?->toISOString(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                    'roles' => $user->getRoleNames()->toArray(),
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'mapConfig' => [
                'provider' => MapSetting::resolvedProvider()->value,
                'osm' => [
                    'tileUrl' => $settings->mapOsmTileUrl,
                    'attribution' => $settings->mapOsmAttribution,
                ],
                // Google Maps JS API keys are meant to be used client-side —
                // they're restricted by HTTP referrer/domain on the Google
                // Cloud console, not a secret like an OAuth client secret.
                'google' => [
                    'apiKey' => $settings->mapGoogleApiKey,
                ],
            ],
        ];
    }
}
