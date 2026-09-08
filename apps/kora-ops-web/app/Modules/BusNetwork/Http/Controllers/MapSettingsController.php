<?php

namespace App\Modules\BusNetwork\Http\Controllers;

use App\Modules\BusNetwork\Actions\UpdateMapSettingAction;
use App\Modules\BusNetwork\Data\MapProvider;
use App\Modules\BusNetwork\Http\Requests\UpdateMapSettingRequest;
use App\Modules\BusNetwork\Models\MapSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MapSettingsController
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->can('network.view') ?? false, 403);

        $setting = MapSetting::current();
        $adminProvider = $setting !== null ? $setting->providerEnum() : null;

        return Inertia::render('network/map-settings', [
            'envDefaultProvider' => config('services.maps.default_provider'),
            'adminOverrideProvider' => $adminProvider?->value,
            'resolvedProvider' => MapSetting::resolvedProvider()->value,
            'providers' => MapProvider::options(),
            'googleConfigured' => filled(config('services.maps.google.api_key')),
        ]);
    }

    public function update(UpdateMapSettingRequest $request): RedirectResponse
    {
        app(UpdateMapSettingAction::class)->execute($request->payload(), $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fournisseur cartographique mis à jour.')]);

        return to_route('network.map-settings.edit');
    }
}
