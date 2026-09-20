<?php

namespace App\Modules\IdentityAccess\Data;

/**
 * Motif d'une fin de session OPS, stocké dans `terminal_sessions.revoked_reason`
 * et renvoyé au mobile dans `error.reason` (jamais de donnée personnelle).
 */
enum OpsSessionRevocationReason: string
{
    /** Déconnexion volontaire du convoyeur. */
    case Logout = 'logout';

    case TerminalDeactivated = 'terminal_deactivated';
    case TerminalLost = 'terminal_lost';
    case TerminalMaintenance = 'terminal_maintenance';

    /** Le terminal a été affecté à un autre utilisateur. */
    case TerminalReassigned = 'terminal_reassigned';

    case AccountSuspended = 'account_suspended';

    /** Rôle ou permission OPS retiré au compte. */
    case AccessRemoved = 'access_removed';

    /** Remplacée par une nouvelle session (autre terminal, autre appareil). */
    case Replaced = 'replaced';

    /** Durée de vie dépassée (fenêtre ou durée absolue). */
    case Expired = 'expired';
}
