<?php

namespace App\Modules\Parcels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\CrewDepartureScope;
use App\Modules\Parcels\Services\ParcelManifest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParcelManifestPrintController extends Controller
{
    public function __invoke(Request $request, Departure $departure, CrewDepartureScope $crew, ParcelManifest $manifest): Response
    {
        $manifest->authorize($request->user(), $departure, $crew);

        return Inertia::render('parcel-manifest', [
            'manifest' => $manifest->build($departure),
        ]);
    }
}
