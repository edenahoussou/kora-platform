<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfilePreferencesUpdateRequest;
use App\Modules\IdentityAccess\Actions\UpdateOpsPreferencesAction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProfilePreferenceController extends Controller
{
    public function __construct(
        private readonly UpdateOpsPreferencesAction $updatePreferences,
    ) {}

    /**
     * Persiste les préférences de l'utilisateur (canaux, alertes, langue, fuseau).
     */
    public function update(ProfilePreferencesUpdateRequest $request): RedirectResponse
    {
        $this->updatePreferences->execute($request->user(), $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Préférences enregistrées.')]);

        return back();
    }
}
