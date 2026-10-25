<?php

namespace App\Modules\AppDistribution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Http\Requests\AppUpdateCheckRequest;
use App\Modules\AppDistribution\Services\AppReleaseCatalog;
use Illuminate\Http\JsonResponse;

/**
 * @group Distribution des applications
 *
 * Vérification de mise à jour au lancement de l'app (MEZ-150).
 */
class AppUpdateCheckController extends Controller
{
    /**
     * Vérifier si une mise à jour est disponible ou obligatoire.
     *
     * Appelé par l'app au démarrage, AVANT toute connexion (un terminal dont
     * la version est obsolète doit être bloqué même sans session). Renvoie
     * le même bloc `update` que les réponses `ops-mobile/terminal/handshake`,
     * `sync` et `activity`. `data` vaut `null` tant qu'aucune version n'est
     * publiée pour l'application.
     *
     * Règle : `mandatory = true` dès qu'une release obligatoire plus récente
     * que la version installée est publiée ; la cible proposée
     * (`download_url`, `sha256`) est toujours la dernière release publiée.
     * Le client DOIT vérifier le `sha256` du fichier téléchargé avant de
     * lancer l'installation.
     *
     * @unauthenticated
     *
     * @urlParam app string required Application (`ops-mobile` ou `ops_mobile`). Example: ops-mobile
     *
     * @response 200 scenario="Mise à jour obligatoire" {
     *   "data": {
     *     "app": "ops_mobile",
     *     "status": "update_required",
     *     "installed_version": "1.2.0",
     *     "installed_build": 30,
     *     "latest_version": "1.4.0",
     *     "latest_build": 42,
     *     "latest_full_version": "1.4.0+42",
     *     "update_available": true,
     *     "mandatory": true,
     *     "minimum_version": "1.3.0",
     *     "minimum_build": 35,
     *     "release_uuid": "6f0c1d3e-7c1b-4a8a-9d6e-2b1a0c9f8e71",
     *     "release_notes": "Correctifs d'impression du manifeste.",
     *     "download_url": "https://ops.koraexpress.app/downloads/apps/ops-mobile/releases/6f0c1d3e-7c1b-4a8a-9d6e-2b1a0c9f8e71",
     *     "download_page_url": "https://ops.koraexpress.app/downloads/apps/ops-mobile",
     *     "sha256": "9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08",
     *     "size_bytes": 31457280,
     *     "published_at": "2026-10-25T09:00:00.000Z"
     *   }
     * }
     * @response 200 scenario="Aucune version publiée" { "data": null }
     * @response 404 scenario="Application inconnue" { "message": "Not Found" }
     */
    public function __invoke(AppUpdateCheckRequest $request, AppReleaseCatalog $catalog, string $app): JsonResponse
    {
        $releasedApp = ReleasedApp::fromRouteKey($app);

        abort_if($releasedApp === null, 404);

        return response()->json([
            'data' => $catalog->check($releasedApp, $request->installedVersion())->toPayload(),
            'meta' => ['server_time' => now()->toISOString()],
        ]);
    }
}
