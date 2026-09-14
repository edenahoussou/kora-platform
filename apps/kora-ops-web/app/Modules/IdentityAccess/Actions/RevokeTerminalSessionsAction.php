<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeTerminalSessionsAction
{
    /**
     * Révoque toutes les sessions OPS Mobile actives d'un terminal : le
     * jeton Sanctum associé à chaque session est supprimé (déconnexion
     * immédiate), et la session est marquée révoquée.
     *
     * @return int Nombre de sessions révoquées.
     */
    public function execute(TerminalP501 $terminal, string $reason): int
    {
        $sessions = TerminalSession::query()
            ->where('terminal_p501_id', $terminal->id)
            ->whereNull('revoked_at')
            ->get();

        foreach ($sessions as $session) {
            if ($session->personal_access_token_id) {
                PersonalAccessToken::query()
                    ->where('id', $session->personal_access_token_id)
                    ->delete();
            }

            $session->forceFill([
                'revoked_at' => now(),
                'revoked_reason' => $reason,
            ])->save();
        }

        return $sessions->count();
    }
}
