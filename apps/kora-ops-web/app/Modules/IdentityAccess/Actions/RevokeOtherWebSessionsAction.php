<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use function activity;

class RevokeOtherWebSessionsAction
{
    /**
     * Déconnecte tous les autres navigateurs : invalide les cookies
     * « remember me » et purge les lignes `sessions` de l'utilisateur.
     */
    public function execute(User $user, string $password, string $currentSessionId): int
    {
        Auth::logoutOtherDevices($password);

        $deleted = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties(['revoked_count' => $deleted])
            ->log('other_web_sessions_revoked');

        return $deleted;
    }
}
