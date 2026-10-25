<?php

namespace App\Modules\AppDistribution\Services;

use App\Modules\AppDistribution\Data\AppUpdateCheck;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\IdentityAccess\Models\TerminalP501;

/**
 * Pont entre un terminal OPS et la distribution hors store (MEZ-150) :
 * calcule le statut de mise à jour à partir de la version que le terminal a
 * remontée (`app_version`, mise à jour par handshake/sync).
 */
class TerminalAppUpdateResolver
{
    public function __construct(private AppReleaseCatalog $catalog) {}

    public function check(TerminalP501 $terminal): AppUpdateCheck
    {
        return $this->catalog->checkRaw(ReleasedApp::forTerminal($terminal), $terminal->app_version);
    }

    /**
     * Bloc `update` des réponses handshake/sync/activity/session — `null`
     * tant qu'aucune release n'est publiée pour l'app du terminal.
     *
     * @return array<string, mixed>|null
     */
    public function payload(TerminalP501 $terminal): ?array
    {
        return $this->check($terminal)->toPayload();
    }
}
