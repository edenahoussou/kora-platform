<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\ManifestDocumentService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Manifeste passagers A4 paysage — prévisualisation HTML imprimable avec
 * barre d'outils écran (Imprimer / Télécharger le PDF). Le même document
 * sort en PDF via manifest.pdf et, à terme, par la route OPS Mobile.
 */
class ManifestPrintController
{
    public function __construct(
        private readonly ManifestDocumentService $documents,
    ) {}

    public function __invoke(Departure $departure): Response
    {
        Gate::authorize('view', $departure);

        return response()->view('manifests.manifest', [
            ...$this->documents->payload($departure),
            'downloadUrl' => route('manifest.pdf', ['departure' => $departure->uuid]),
        ]);
    }
}
