<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\KoraPoints\Http\Requests\ExecuteKoraPointRefundRequest;
use App\Modules\KoraPoints\Http\Requests\RequestKoraPointRefundRequest;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use App\Modules\KoraPoints\Services\KoraPointRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KoraPointRefundController extends Controller
{
    public function __construct(private readonly KoraPointRefundService $service, private readonly KoraPointAccess $access) {}

    /**
     * Renders a single refund detail page.
     */
    public function show(Request $request, KoraPointRefund $refund): Response
    {
        $this->access->requireSale($request->user(), $refund->sale);

        return Inertia::render('kora-points/refunds/show', ['refund' => [
            'uuid' => $refund->uuid, 'sale_uuid' => $refund->sale->uuid, 'status' => $refund->status, 'amount' => $refund->amount,
            'executor' => $refund->executor, 'method' => $refund->method, 'reference' => $refund->reference,
            'beneficiary' => $refund->beneficiary, 'reason' => $refund->reason, 'executed_at' => $refund->executed_at?->toIso8601String(), 'confirmed_at' => $refund->confirmed_at?->toIso8601String(),
        ]]);
    }

    /**
     * Files a refund request on a sale then redirects to the refund detail.
     */
    public function store(RequestKoraPointRefundRequest $request, KoraPointSale $sale): RedirectResponse
    {
        $refund = $this->service->request($request->user(), $sale, $request->validated('reason'), $request->validated('passenger_uuid'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Demande transmise à KORA.']);

        return to_route('kora-points.refunds.show', $refund->uuid);
    }

    /**
     * Finance approves the refund for restitution.
     */
    public function authorizeRefund(Request $request, KoraPointRefund $refund): RedirectResponse
    {
        $this->service->authorize($request->user(), $refund);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Remboursement autorisé.']);

        return back();
    }

    /**
     * Finance rejects the refund request.
     */
    public function reject(Request $request, KoraPointRefund $refund): RedirectResponse
    {
        $this->service->authorize($request->user(), $refund, true);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Demande refusée.']);

        return back();
    }

    /**
     * Declares the effective restitution of funds to the beneficiary.
     */
    public function execute(ExecuteKoraPointRefundRequest $request, KoraPointRefund $refund): RedirectResponse
    {
        $this->service->execute($request->user(), $refund, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Restitution déclarée ; confirmation en attente.']);

        return back();
    }

    /**
     * Confirms the restitution and posts the ledger reversal.
     */
    public function confirm(Request $request, KoraPointRefund $refund): RedirectResponse
    {
        $this->service->confirm($request->user(), $refund);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Restitution confirmée.']);

        return back();
    }
}
