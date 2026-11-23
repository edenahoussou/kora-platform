<?php

namespace App\Modules\Parcels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Parcels\Actions\GenerateParcelLabelPdfAction;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Services\ParcelAccess;
use App\Modules\Parcels\Services\ParcelLabelDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Bordereau d’envoi A4 paysage, en deux volets séparables.
 *
 * La page HTML et le PDF partagent la même vue Blade. L’impression reste une
 * opération de dépôt : un agent `parcels.intake` rattaché au hub de custody
 * (ou, avant custody, au hub de dépôt) est requis.
 */
class ParcelLabelController extends Controller
{
    public function __construct(
        private readonly ParcelAccess $access,
        private readonly ParcelLabelDocument $document,
        private readonly GenerateParcelLabelPdfAction $pdf,
    ) {}

    public function show(Request $request, Parcel $parcel): Response
    {
        [$parcel, $hub] = $this->authorizeLabel($request, $parcel);
        $payload = $this->document->build($parcel);
        $this->recordPrintRequest($parcel, $hub, $request->user());

        return response()
            ->view('parcels.label', [
                ...$payload,
                'back_url' => route('parcels'),
                'pdf_url' => route('parcels.label.pdf', ['parcel' => $parcel->uuid]),
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function pdf(Request $request, Parcel $parcel): Response
    {
        [$parcel, $hub] = $this->authorizeLabel($request, $parcel);
        $payload = $this->document->build($parcel);
        $this->recordPrintRequest($parcel, $hub, $request->user());

        try {
            $generated = $this->pdf->render($payload, (string) $parcel->reference);
        } catch (Throwable $exception) {
            report($exception);
            abort(500, 'Le bordereau n’a pas pu être généré.');
        }

        return response($generated['content'], Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$generated['filename'].'"',
            'Content-Length' => (string) strlen($generated['content']),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{0: Parcel, 1: ParcelHub} */
    private function authorizeLabel(Request $request, Parcel $parcel): array
    {
        $scoped = $this->access->operations($request->user())
            ->with([
                'corridor.originHub.stop',
                'corridor.destinationHub.stop',
                'depositHub.stop',
                'custodyHub.stop',
            ])
            ->findOrFail($parcel->id);

        abort_unless(
            ParcelLabelDocument::statusAllowsPrinting($scoped->status),
            409,
            'Le statut du colis ne permet pas le bordereau.',
        );

        $hub = $scoped->custodyHub ?? $scoped->depositHub ?? $scoped->corridor?->originHub;
        abort_if($hub === null, 409, 'Le hub d’impression est indisponible.');

        $this->access->atHub($request->user(), $hub, 'parcels.intake');

        return [$scoped, $hub];
    }

    private function recordPrintRequest(Parcel $parcel, ParcelHub $hub, User $actor): void
    {
        $parcel->record('print_requested', $actor, $hub, details: [
            'kind' => 'label_a4',
            'format' => 'a4_landscape',
            'channel' => 'document',
            'template_version' => 1,
        ]);
    }
}
