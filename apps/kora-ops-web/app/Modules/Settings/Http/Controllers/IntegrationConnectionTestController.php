<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\Services\IntegrationConnectionProbeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

use function Illuminate\Support\enum_value;

final class IntegrationConnectionTestController
{
    public function __invoke(
        Request $request,
        string $provider,
        IntegrationConnectionProbeManager $probes,
    ): RedirectResponse {
        $actor = $request->user();

        abort_unless($actor instanceof User && enum_value($actor->status) === UserStatus::Active->value, 403);

        Gate::forUser($actor)->authorize(SettingsGroup::Integrations->managePermission());

        $result = $probes->test($provider);

        activity('settings')
            ->causedBy($actor)
            ->event('settings.connection_tested')
            ->withProperties([
                'provider' => $result->provider,
                'status' => $result->status,
                'environment' => $result->environment,
                'durationMs' => $result->durationMs,
                'safeMessage' => $result->safeMessage,
            ])
            ->log('settings.connection_tested');

        Inertia::flash('toast', [
            'type' => $result->status === 'success' ? 'success' : 'error',
            'message' => $result->safeMessage,
        ]);

        return back();
    }
}
