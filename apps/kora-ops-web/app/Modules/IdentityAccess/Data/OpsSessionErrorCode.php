<?php

namespace App\Modules\IdentityAccess\Data;

/**
 * Codes fonctionnels des refus de session OPS Mobile (en complément des codes
 * terminal_* déjà portés par TerminalUnknownException et
 * TerminalNotUsableException). Jamais d'identifiant numérique interne.
 */
enum OpsSessionErrorCode: string
{
    case SessionNotOpened = 'session_not_opened';
    case SessionExpired = 'session_expired';
    case SessionRevoked = 'session_revoked';
    case SessionInvalid = 'session_invalid';
    case TerminalMismatch = 'terminal_mismatch';
    case TerminalNotAssigned = 'terminal_not_assigned';
    case RoleNotAuthorized = 'role_not_authorized';
    case PermissionDenied = 'permission_denied';
    case AccountSuspended = 'account_suspended';
    case AccountLocked = 'account_locked';

    public function httpStatus(): int
    {
        return match ($this) {
            self::SessionNotOpened,
            self::SessionExpired,
            self::SessionRevoked,
            self::SessionInvalid => 401,
            default => 403,
        };
    }

    public function action(): OpsSessionAction
    {
        return match ($this) {
            self::SessionNotOpened, self::TerminalMismatch => OpsSessionAction::OpenSession,
            self::SessionExpired, self::SessionRevoked, self::SessionInvalid => OpsSessionAction::Reauthenticate,
            self::AccountLocked => OpsSessionAction::RetryLater,
            self::TerminalNotAssigned,
            self::RoleNotAuthorized,
            self::PermissionDenied,
            self::AccountSuspended => OpsSessionAction::ContactAgency,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::SessionNotOpened => 'Aucune session OPS n’est ouverte pour ce terminal. Ouvrez une session.',
            self::SessionExpired => 'Votre session a expiré. Reconnectez-vous.',
            self::SessionRevoked => 'Votre session a été fermée. Reconnectez-vous.',
            self::SessionInvalid => 'Session invalide. Reconnectez-vous.',
            self::TerminalMismatch => 'Cette session est liée à un autre terminal. Ouvrez une session sur ce terminal.',
            self::TerminalNotAssigned => 'Ce terminal ne vous est pas affecté. Contactez votre agence.',
            self::RoleNotAuthorized => 'Votre compte n’a pas de rôle autorisé sur KORA OPS Mobile.',
            self::PermissionDenied => 'Votre compte n’a pas la permission d’utiliser KORA OPS Mobile.',
            self::AccountSuspended => 'Ce compte est suspendu. Contactez votre agence.',
            self::AccountLocked => 'Ce compte est temporairement verrouillé. Réessayez plus tard.',
        };
    }
}
