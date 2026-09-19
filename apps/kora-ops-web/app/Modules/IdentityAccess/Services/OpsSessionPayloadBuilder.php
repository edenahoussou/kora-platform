<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Http\Resources\AuthUserResource;
use App\Modules\IdentityAccess\Http\Resources\OpsSessionResource;
use App\Modules\IdentityAccess\Http\Resources\TerminalP501Resource;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use Illuminate\Http\Request;

/**
 * Corps `data` commun à l'ouverture (handshake), à la consultation et au
 * renouvellement d'une session : les champs du terminal restent à la racine
 * (contrat handshake antérieur), la session, l'utilisateur et ses
 * permissions OPS sont ajoutés à côté.
 */
class OpsSessionPayloadBuilder
{
    public function __construct(private OpsSessionGuard $guard) {}

    /**
     * @return array<string, mixed>
     */
    public function build(TerminalSession $session, TerminalP501 $terminal, User $user, Request $request): array
    {
        $terminal->loadMissing(['assignedUser', 'agency']);

        return [
            ...(new TerminalP501Resource($terminal))->resolve($request),
            'session' => (new OpsSessionResource($session))->resolve($request),
            'capabilities' => $this->guard->capabilities($user),
            'user' => (new AuthUserResource($user))->resolve($request),
        ];
    }
}
