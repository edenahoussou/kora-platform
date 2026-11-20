<?php

namespace App\Modules\Parcels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Parcels\Services\ParcelAccess;
use App\Modules\Parcels\Services\ParcelSupervision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @group KORA OPS Supervision Colis */
class ParcelSupervisionController extends Controller
{
    public function __construct(
        private readonly ParcelAccess $access,
        private readonly ParcelSupervision $supervision,
    ) {}

    /**
     * Vue agrégée de supervision Colis.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function index(Request $request): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.view');

        return response()->json(['data' => $this->supervision->overview($request->user())]);
    }

    /**
     * Export CSV agrégé de supervision, sans données personnelles ni secrets.
     *
     * @authenticated
     *
     * @response 200
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function export(Request $request): StreamedResponse
    {
        $this->access->require($request->user(), 'parcels.view');
        $rows = $this->supervision->exportRows($request->user());
        activity('parcels')
            ->causedBy($request->user())
            ->withProperties(['format' => 'csv', 'rows' => count($rows)])
            ->log('parcel_supervision_exported');

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, ['Section', 'Type', 'Libellé', 'Valeur', 'Montant']);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'kora-supervision-colis-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
