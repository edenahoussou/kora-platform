<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\TerminalActivityType;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;

class HandshakeTerminalAction
{
    public function __construct(
        private LogTerminalActivityAction $logActivity,
    ) {}

    /**
     * Ouvre (ou renouvelle) la session OPS Mobile d'un terminal pour
     * l'utilisateur authentifié : associe l'identifiant du jeton Sanctum
     * courant au terminal (pour permettre une révocation ciblée) et met à
     * jour la date de dernier accès du terminal.
     */
    public function execute(TerminalP501 $terminal, User $user, ?int $tokenId): TerminalP501
    {
        TerminalSession::query()->create([
            'terminal_p501_id' => $terminal->id,
            'user_id' => $user->id,
            'personal_access_token_id' => $tokenId,
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        $terminal->update(['last_handshake_at' => now()]);

        $this->logActivity->execute($terminal, $user, TerminalActivityType::Handshake);

        return $terminal->refresh();
    }
}
