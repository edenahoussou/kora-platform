<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OpsSessionErrorCode;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Data\TerminalActivityType;
use App\Modules\IdentityAccess\Exceptions\OpsSessionException;
use App\Modules\IdentityAccess\Exceptions\TerminalNotUsableException;
use App\Modules\IdentityAccess\Exceptions\TerminalUnknownException;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\IdentityAccess\Services\OpsSessionGuard;
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

use function activity;

class HandshakeTerminalAction
{
    public function __construct(
        private ResolveTerminalBySerialAction $resolveTerminal,
        private OpsSessionGuard $guard,
        private RevokeOpsSessionAction $revokeSession,
        private LogTerminalActivityAction $logActivity,
        private LogOpsSessionRefusalAction $logRefusal,
    ) {}

    /**
     * Ouvre la session OPS Mobile d'un utilisateur sur un terminal : lie le
     * jeton Sanctum courant au terminal (et à son numéro de série) après
     * avoir vérifié, dans cet ordre, le terminal (connu et actif), le compte
     * (actif, non verrouillé), le rôle terrain, la permission et
     * l'affectation du terminal à cet utilisateur.
     *
     * Une seule session active par utilisateur et par terminal : ouvrir une
     * session ferme les précédentes (motif `replaced`). Le jeton d'une autre
     * session de l'utilisateur (autre appareil) est supprimé ; celui qui sert
     * à cette ouverture est conservé.
     *
     * Tout refus est journalisé (`ops_session_refused`) puis relancé.
     *
     * @throws TerminalUnknownException|TerminalNotUsableException|OpsSessionException
     */
    public function execute(string $serialNumber, User $user, ?int $tokenId, ?string $ipAddress = null): TerminalSession
    {
        $terminal = null;

        try {
            $terminal = $this->resolveTerminal->execute($serialNumber);

            if (! $terminal->isUsable()) {
                throw new TerminalNotUsableException($terminal);
            }

            if ($tokenId === null) {
                throw new OpsSessionException(OpsSessionErrorCode::SessionInvalid);
            }

            $this->guard->assertUserAllowed($user);
            $this->guard->assertTerminalAssignedTo($terminal, $user);
        } catch (OpsSessionException|TerminalNotUsableException|TerminalUnknownException $exception) {
            $this->logRefusal->execute($exception, $user, $terminal, $serialNumber, $ipAddress);

            throw $exception;
        }

        $session = DB::transaction(function () use ($terminal, $user, $tokenId): TerminalSession {
            $now = now();
            $settings = app(OperationsSettings::class);
            $maxLifetime = $settings->opsSessionMaxLifetimeMinutes;
            $window = min($settings->opsSessionTtlMinutes, $maxLifetime);

            $previousSessions = TerminalSession::query()
                ->active()
                ->where(function (Builder $query) use ($user, $terminal): void {
                    $query->where('user_id', $user->id)
                        ->orWhere('terminal_p501_id', $terminal->id);
                })
                ->get();

            foreach ($previousSessions as $previous) {
                $this->revokeSession->execute(
                    $previous,
                    OpsSessionRevocationReason::Replaced,
                    $user,
                    $previous->personal_access_token_id !== $tokenId,
                );
            }

            $session = TerminalSession::query()->create([
                'terminal_p501_id' => $terminal->id,
                'user_id' => $user->id,
                'personal_access_token_id' => $tokenId,
                'started_at' => $now,
                'last_seen_at' => $now,
                'expires_at' => $now->copy()->addMinutes($window),
                'absolute_expires_at' => $now->copy()->addMinutes($maxLifetime),
                'renewal_count' => 0,
            ]);

            $terminal->update(['last_handshake_at' => $now]);

            return $session;
        });

        $properties = [
            'session_uuid' => $session->uuid,
            'expires_at' => $session->expires_at?->toISOString(),
        ];

        $this->logActivity->execute($terminal, $user, TerminalActivityType::Handshake, $properties);

        activity()
            ->performedOn($terminal)
            ->causedBy($user)
            ->withProperties([...$properties, 'serial_number' => $terminal->serial_number, 'ip' => $ipAddress])
            ->log('ops_session_opened');

        return $session->setRelation('terminal', $terminal);
    }
}
