<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\Notifications\Services\OpsNotifier;
use Laravel\Sanctum\PersonalAccessToken;

use function activity;

class RevokeOpsSessionAction
{
    /** Motifs de révocation qui relèvent du terminal (et déclenchent l'alerte « terminal révoqué »). */
    private const array TERMINAL_REASONS = [
        'terminal_deactivated',
        'terminal_lost',
        'terminal_maintenance',
        'terminal_reassigned',
    ];

    public function __construct(
        private OpsNotifier $notifier,
    ) {}

    /**
     * Termine une session OPS : supprime le jeton Sanctum associé
     * (déconnexion immédiate), marque la session révoquée avec son motif et
     * journalise l'événement (`ops_session_closed` pour une déconnexion,
     * `ops_session_expired` pour une expiration, `ops_session_revoked` sinon).
     * Sans effet si la session est déjà révoquée.
     *
     * @param  bool  $deleteToken  `false` quand le même jeton sert à ouvrir la
     *                             session suivante (remplacement sur un autre terminal).
     */
    public function execute(
        TerminalSession $session,
        OpsSessionRevocationReason|string $reason,
        ?User $actor = null,
        bool $deleteToken = true,
    ): void {
        if ($session->revoked_at !== null) {
            return;
        }

        $reasonValue = $reason instanceof OpsSessionRevocationReason ? $reason->value : $reason;

        if ($deleteToken && $session->personal_access_token_id !== null) {
            PersonalAccessToken::query()
                ->where('id', $session->personal_access_token_id)
                ->delete();
        }

        $session->forceFill([
            'revoked_at' => now(),
            'revoked_reason' => $reasonValue,
        ])->save();

        $session->loadMissing(['terminal', 'user']);

        activity()
            ->performedOn($session->terminal)
            ->causedBy($actor ?? $session->user)
            ->withProperties([
                'session_uuid' => $session->uuid,
                'serial_number' => $session->terminal->serial_number,
                'reason' => $reasonValue,
            ])
            ->log($this->description($reasonValue));

        if (in_array($reasonValue, self::TERMINAL_REASONS, true)) {
            $this->notifier->terminalRevoked($session, $reasonValue);
        }
    }

    private function description(string $reason): string
    {
        return match ($reason) {
            OpsSessionRevocationReason::Logout->value => 'ops_session_closed',
            OpsSessionRevocationReason::Expired->value => 'ops_session_expired',
            default => 'ops_session_revoked',
        };
    }
}
