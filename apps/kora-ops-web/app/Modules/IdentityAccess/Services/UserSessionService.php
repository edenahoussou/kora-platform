<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\ParseSessionUserAgentAction;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\IdentityAccess\Models\UserDevice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserSessionService
{
    public function __construct(
        private UserDeviceService $userDeviceService,
        private ParseSessionUserAgentAction $parseUserAgent,
    ) {}

    /**
     * Sessions et appareils actifs de l'utilisateur : sessions web
     * (table `sessions`), sessions P501 et appareils de confiance.
     * L'identifiant exposé des sessions web est hashé (jamais l'id brut).
     *
     * @return Collection<int, array{id: string, kind: string, device: string, type: string, browser: string|null, ip: string|null, location: string|null, isCurrent: bool, lastActive: string|null}>
     */
    public function listForUser(User $user, string $currentSessionId): Collection
    {
        return collect()
            ->merge($this->webSessions($user, $currentSessionId))
            ->merge($this->terminalSessions($user))
            ->merge($this->trustedDevices($user))
            ->values();
    }

    /**
     * Résout l'identifiant de session brut depuis son empreinte sha256
     * exposée au frontend. Null si inconnue ou déjà expirée.
     */
    public function resolveSessionId(User $user, string $hashedId): ?string
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->pluck('id')
            ->first(fn (string $id) => hash_equals(hash('sha256', $id), $hashedId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function webSessions(User $user, string $currentSessionId): array
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function (object $session) use ($currentSessionId): array {
                $parsed = $this->parseUserAgent->execute($session->user_agent);

                return [
                    'id' => hash('sha256', $session->id),
                    'kind' => 'web',
                    'device' => $parsed['device'],
                    'type' => $parsed['type'],
                    'browser' => $parsed['browser'],
                    'ip' => $session->ip_address,
                    'location' => null,
                    'isCurrent' => $session->id === $currentSessionId,
                    'lastActive' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function terminalSessions(User $user): array
    {
        return TerminalSession::query()
            ->active()
            ->where('user_id', $user->id)
            ->with('terminal')
            ->get()
            ->map(fn (TerminalSession $session): array => [
                'id' => $session->uuid,
                'kind' => 'terminal',
                'device' => 'Terminal KORA '.$session->terminal->serial_number,
                'type' => 'pos',
                'browser' => 'KORA OPS Mobile (P501)',
                'ip' => null,
                'location' => $session->terminal->agency?->name,
                'isCurrent' => false,
                'lastActive' => $session->last_seen_at?->diffForHumans(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function trustedDevices(User $user): array
    {
        return $this->userDeviceService
            ->listForUser($user)
            ->map(fn (UserDevice $device): array => [
                'id' => $device->uuid,
                'kind' => 'device',
                'device' => $device->name,
                'type' => $device->platform->value === 'web' ? 'desktop' : 'mobile',
                'browser' => null,
                'ip' => null,
                'location' => null,
                'isCurrent' => false,
                'lastActive' => $device->last_seen_at?->diffForHumans(),
            ])
            ->all();
    }
}
