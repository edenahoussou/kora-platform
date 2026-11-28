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
 * Vérification de mise à jour au lancement de l'app (#342).
 */
class AppUpdateCheckController extends Controller
{
    /**
     * Vérifier si une mise à jour est disponible ou obligatoire.
     *
     * Appelé par l'app au démarrage, AVANT toute connexion. `data` vaut `null`
     * seulement lorsqu'aucune release publique confirmée n'existe pour le
     * triplet `app × platform × environment`. Si une release confirmée existe
     * et que l'installée est à jour, la réponse contient un objet
     * `status: "up_to_date"` plutôt que `null`.
     *
     * Pour le canal store (`passenger_mobile`), `platform` est obligatoire.
     * `environment` est optionnel et vaut `production` par défaut côté
     * serveur. Aucun binaire, empreinte ou secret FCM n'est exposé par cette
     * réponse ; le mobile ouvre le `store_url` officiel.
     *
     * @unauthenticated
     *
     * @urlParam app string required Application (`ops-mobile`, `ops_mobile`, `passenger-mobile` ou `passenger_mobile`). Example: passenger-mobile
     *
     * @response 200 scenario="Release store à jour" {
     *   "data": {
     *     "app": "passenger_mobile",
     *     "channel": "store",
     *     "platform": "android",
     *     "environment": "production",
     *     "status": "up_to_date",
     *     "installed_version": "1.4.0",
     *     "installed_build": 42,
     *     "latest_version": "1.4.0",
     *     "latest_build": 42,
     *     "latest_full_version": "1.4.0+42",
     *     "update_available": false,
     *     "mandatory": false,
     *     "minimum_version": null,
     *     "minimum_build": null,
     *     "release_uuid": "6f0c1d3e-7c1b-4a8a-9d6e-2b1a0c9f8e71",
     *     "release_notes": "Version stable disponible sur le store.",
     *     "store_url": "https://play.google.com/store/apps/details?id=app.kora.passenger",
     *     "published_at": "2026-11-28T09:00:00.000Z"
     *   }
     * }
     * @response 200 scenario="Aucune release store confirmée" { "data": null }
     * @response 422 scenario="Requête invalide" { "message": "The platform field is required." }
     * @response 404 scenario="Application inconnue" { "message": "Not Found" }
     */
    public function __invoke(AppUpdateCheckRequest $request, AppReleaseCatalog $catalog, string $app): JsonResponse
    {
        $releasedApp = ReleasedApp::fromRouteKey($app);

        abort_if($releasedApp === null, 404);

        if ($releasedApp === ReleasedApp::PassengerMobile) {
            $platform = $request->platform();
            abort_if($platform === null, 422, 'The platform field is required for store releases.');

            $check = $catalog->checkStore(
                app: $releasedApp,
                platform: $platform,
                environment: $request->environment(),
                installed: $request->installedVersion(),
            );
        } else {
            $check = $catalog->check($releasedApp, $request->installedVersion());
        }

        return response()->json([
            'data' => $check->toPayload(),
            'meta' => ['server_time' => now()->toISOString()],
        ]);
    }
}
