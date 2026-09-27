<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Services\FleetTrackingQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group OPS Web — Suivi de la flotte
 *
 * Positions des départs actuellement en circulation, pour la carte OPS Web
 * (ticket MEZ-100). Protégé par permission ET par périmètre : un
 * gestionnaire d'agence (`tracking.view-agency`) ne voit que sa propre
 * agence, un rôle réseau (`tracking.view-network`) voit toute la flotte
 * (filtrable par agence). Ne retourne que des UUID, des codes métier et les
 * données nécessaires à la carte — aucun identifiant numérique interne.
 */
class FleetTrackingController extends Controller
{
    /**
     * @queryParam agency string UUID d'agence — rôles à périmètre réseau uniquement. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     */
    public function index(Request $request, FleetTrackingQuery $fleetQuery): JsonResponse
    {
        $user = $request->user();

        $sessions = $fleetQuery
            ->build($user, ['agency' => (string) $request->string('agency')])
            ->get();

        return response()->json(['data' => $fleetQuery->present($sessions)]);
    }
}
