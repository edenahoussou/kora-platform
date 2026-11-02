<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Laravel\Fortify\Events\PasswordUpdatedViaController;

use function activity;

class SecurityController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->password,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        if ($user->isKoraPointUser()) {
            $invitation = $user->latestKoraPointWebAccessInvitation()->first();

            if ($invitation?->status === KoraPointWebAccessInvitation::STATUS_SENT) {
                $invitation->update([
                    'status' => KoraPointWebAccessInvitation::STATUS_USED,
                    'used_at' => now(),
                ]);
            }
        }

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($user)
            ->log('password_changed');

        PasswordUpdatedViaController::dispatch($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        if ($user->isKoraPointUser()) {
            if ($user->hasRole('kora_point_agent')) {
                return to_route('kora-point.dashboard');
            }

            if ($user->hasAnyRole(['kora_point_supervisor', 'kora_point_treasurer'])) {
                return to_route('kora-partner.dashboard');
            }
        }

        return back();
    }
}
