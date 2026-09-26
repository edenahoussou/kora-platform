<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Modules\Departures\Actions\GenerateManifestPdfAction;
use App\Modules\Departures\Exceptions\BorderManifestIncompleteException;
use App\Modules\Departures\Models\Departure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Téléchargement du manifeste passagers en PDF A4 paysage (web).
 * Bouton « Télécharger le PDF » de la vue d'impression — même document
 * que manifest.print, sorti en binaire via Browsershot.
 */
class ManifestPdfController
{
    public function __invoke(Departure $departure, GenerateManifestPdfAction $generatePdf): Response
    {
        Gate::authorize('view', $departure);

        try {
            $pdf = $generatePdf->execute($departure);
        } catch (BorderManifestIncompleteException $exception) {
            // Rendue en 422 manifest_incomplete par l'exception elle-même.
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            abort(500, "Le manifeste n'a pas pu être généré.");
        }

        return response($pdf['content'], Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
            'Content-Length' => (string) strlen($pdf['content']),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
