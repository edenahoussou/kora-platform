<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function activity;

class RevokeWebSessionAction
{
    /**
     * Supprime une session web de l'utilisateur ; la session courante
     * ne peut jamais être révoquée par ce biais.
     */
    public function execute(User $user, string $sessionId, string $currentSessionId): void
    {
        if ($sessionId === $currentSessionId) {
            throw new NotFoundHttpException;
        }

        $deleted = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted === 0) {
            throw new NotFoundHttpException;
        }

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($user)
            ->log('web_session_revoked');
    }
}
