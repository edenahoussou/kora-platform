<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Models\User;
use App\Modules\Settings\Contracts\SettingsCoordinator;
use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\DTO\GeneralSettingsUpdate;
use App\Modules\Settings\DTO\IntegrationsSettingsUpdate;
use App\Modules\Settings\DTO\OperationsSettingsUpdate;
use App\Modules\Settings\Http\Requests\UpdateGeneralSettingsRequest;
use App\Modules\Settings\Http\Requests\UpdateIntegrationsSettingsRequest;
use App\Modules\Settings\Http\Requests\UpdateOperationsSettingsRequest;
use App\Modules\Settings\Services\SettingsPagePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController
{
    public function __construct(private readonly SettingsCoordinator $settings) {}

    public function index(Request $request): RedirectResponse
    {
        foreach (SettingsGroup::cases() as $group) {
            if ($request->user()?->can($group->viewPermission())) {
                return to_route('administration.settings.'.$group->value);
            }
        }

        abort(403);
    }

    public function general(Request $request, SettingsPagePresenter $presenter): Response
    {
        return Inertia::render('administration/settings', $presenter->page(SettingsGroup::General, $this->actor($request)));
    }

    public function operations(Request $request, SettingsPagePresenter $presenter): Response
    {
        return Inertia::render('administration/settings', $presenter->page(SettingsGroup::Operations, $this->actor($request)));
    }

    public function integrations(Request $request, SettingsPagePresenter $presenter): Response
    {
        return Inertia::render('administration/settings', $presenter->page(SettingsGroup::Integrations, $this->actor($request)));
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $this->settings->update(
            SettingsGroup::General,
            new GeneralSettingsUpdate($request->values(), $request->clearSecrets()),
            $request->user(),
        );

        return $this->saved('administration.settings.general');
    }

    public function updateOperations(UpdateOperationsSettingsRequest $request): RedirectResponse
    {
        $this->settings->update(
            SettingsGroup::Operations,
            new OperationsSettingsUpdate($request->values(), $request->clearSecrets()),
            $request->user(),
        );

        return $this->saved('administration.settings.operations');
    }

    public function updateIntegrations(UpdateIntegrationsSettingsRequest $request): RedirectResponse
    {
        $this->settings->update(
            SettingsGroup::Integrations,
            new IntegrationsSettingsUpdate($request->values(), $request->clearSecrets()),
            $request->user(),
        );

        return $this->saved('administration.settings.integrations');
    }

    private function saved(string $route): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Les paramètres ont été enregistrés.',
        ]);

        return to_route($route);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
