<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
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

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($user)
            ->log('password_changed');

        PasswordUpdatedViaController::dispatch($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
