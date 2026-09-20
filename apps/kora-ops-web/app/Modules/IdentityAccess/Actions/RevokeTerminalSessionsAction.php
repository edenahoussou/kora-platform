<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;

class RevokeTerminalSessionsAction
{
    public function __construct(
        private RevokeOpsSessionAction $revokeSession,
    ) {}

    /**
     * Révoque toutes les sessions OPS Mobile actives d'un terminal : le
     * jeton Sanctum associé à chaque session est supprimé (déconnexion
     * immédiate), la session est marquée révoquée et chaque révocation est
     * journalisée.
     *
     * @return int Nombre de sessions révoquées.
     */
    public function execute(TerminalP501 $terminal, string $reason): int
    {
        $sessions = TerminalSession::query()
            ->active()
            ->where('terminal_p501_id', $terminal->id)
            ->get();

        foreach ($sessions as $session) {
            $this->revokeSession->execute($session, $reason);
        }

        return $sessions->count();
    }
}
