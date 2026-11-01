<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\KoraPoints\Http\Requests\StoreKoraPartnerDocumentRequest;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use App\Modules\KoraPoints\Services\KoraPointDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KoraPartnerDocumentController extends Controller
{
    public function __construct(private readonly KoraPointDocumentService $service) {}

    /**
     * Stores a document attached to a point.
     */
    public function point(StoreKoraPartnerDocumentRequest $request, KoraPoint $point): RedirectResponse
    {
        $this->service->store($request->user(), $point, $request->validated(), $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document enregistré.']);

        return back();
    }

    /**
     * Stores a document attached to an organization.
     */
    public function organization(StoreKoraPartnerDocumentRequest $request, KoraPointOrganization $organization): RedirectResponse
    {
        $this->service->store($request->user(), $organization, $request->validated(), $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document enregistré.']);

        return back();
    }

    /**
     * Stores the proof file attached to a remittance slip.
     */
    public function remittance(StoreKoraPartnerDocumentRequest $request, KoraPointRemittance $remittance): RedirectResponse
    {
        $this->service->store($request->user(), $remittance, $request->validated(), $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Preuve enregistrée.']);

        return back();
    }

    /**
     * Stores the proof file attached to a refund request.
     */
    public function refund(StoreKoraPartnerDocumentRequest $request, KoraPointRefund $refund): RedirectResponse
    {
        $this->service->store($request->user(), $refund, $request->validated(), $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Preuve enregistrée.']);

        return back();
    }

    /**
     * Streams the document file after checking the actor visibility rights.
     */
    public function download(Request $request, KoraPartnerDocument $document): BinaryFileResponse
    {
        $this->service->authorizeDownload($request->user(), $document);
        $media = $document->getFirstMedia('proof');
        abort_if($media === null, 404);

        return response()->download($media->getPath(), $media->file_name, ['Cache-Control' => 'private, no-store']);
    }
}
