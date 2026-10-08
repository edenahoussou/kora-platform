<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileAvatarUpdateRequest;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Modules\IdentityAccess\Actions\DeleteUserAvatarAction;
use App\Modules\IdentityAccess\Actions\UpdateOpsProfileAction;
use App\Modules\IdentityAccess\Actions\UpdateUserAvatarAction;
use App\Modules\IdentityAccess\Data\SecurityAlertEvent;
use App\Modules\IdentityAccess\Services\OpsProfileService;
use App\Notifications\IdentityAccess\SecurityAlertNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly OpsProfileService $opsProfile,
        private readonly UpdateOpsProfileAction $updateProfile,
        private readonly UpdateUserAvatarAction $updateAvatar,
        private readonly DeleteUserAvatarAction $deleteAvatar,
    ) {}

    /**
     * Hub profil unifié : identité, sécurité, sessions et préférences.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            ...$this->opsProfile->props($request->user(), $request->session()->getId()),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Met à jour l'identité du compte (l'email reste verrouillé DSI).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $result = $this->updateProfile->execute($request->user(), $request->payload());

        if ($result['phone_changed']) {
            $result['user']->notify(new SecurityAlertNotification(SecurityAlertEvent::PhoneChanged, [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return back();
    }

    /**
     * Remplace l'avatar par l'image recadrée envoyée depuis le cropper.
     */
    public function updateAvatar(ProfileAvatarUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $this->updateAvatar->execute($user, $request->file('avatar'));

        $user->notify(new SecurityAlertNotification(SecurityAlertEvent::AvatarUpdated, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo de profil mise à jour.')]);

        return back();
    }

    /**
     * Supprime l'avatar courant.
     */
    public function destroyAvatar(Request $request): RedirectResponse
    {
        $this->deleteAvatar->execute($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo de profil supprimée.')]);

        return back();
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
