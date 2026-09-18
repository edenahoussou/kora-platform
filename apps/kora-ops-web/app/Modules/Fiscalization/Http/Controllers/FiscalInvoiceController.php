<?php

namespace App\Modules\Fiscalization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\FinanceExportService;
use App\Modules\Fiscalization\Actions\RetryFiscalInvoiceAction;
use App\Modules\Fiscalization\Http\Requests\ListFiscalInvoicesRequest;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Models\FiscalSyncAttempt;
use App\Modules\Fiscalization\Services\FiscalInvoiceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalInvoiceController extends Controller
{
    public function index(ListFiscalInvoicesRequest $request, FiscalInvoiceQuery $invoiceQuery): Response
    {
        $filters = $request->filters();
        $invoices = $invoiceQuery->build($filters)->paginate(20)->withQueryString();
        $invoiceDetail = null;
        if ($request->filled('invoice')) {
            $invoice = FiscalInvoice::query()->with(['reservation', 'payment', 'syncAttempts' => fn ($query) => $query->oldest('attempt_number')])->where('uuid', $request->string('invoice')->value())->firstOrFail();
            Gate::authorize('view', $invoice);
            $invoiceDetail = $this->serializeDetail($invoice, $request->user()->can('fiscalization.payload.view'));
        }

        return Inertia::render('fiscal-invoices', [
            'invoices' => $invoices->through(fn (FiscalInvoice $invoice): array => $this->serializeListItem($invoice)),
            'filters' => $filters,
            'statuses' => $invoiceQuery->statuses(),
            'counters' => $invoiceQuery->counters($filters),
            'permissions' => ['canRetry' => $request->user()->can('fiscalization.retry')],
            'invoiceDetail' => $invoiceDetail,
        ]);
    }

    public function show(Request $request, FiscalInvoice $fiscalInvoice): RedirectResponse
    {
        Gate::authorize('view', $fiscalInvoice);
        $fiscalInvoice->load(['reservation', 'payment', 'syncAttempts' => fn ($query) => $query->oldest('attempt_number')]);

        return to_route('fiscal-invoices.index', ['invoice' => $fiscalInvoice->uuid]);
    }

    public function retry(FiscalInvoice $fiscalInvoice, RetryFiscalInvoiceAction $retry): RedirectResponse
    {
        Gate::authorize('retry', $fiscalInvoice);
        $retry->execute($fiscalInvoice, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('La relance fiscale a été mise en file.')]);

        return back();
    }

    public function export(ListFiscalInvoicesRequest $request, FiscalInvoiceQuery $invoiceQuery, FinanceExportService $exporter): StreamedResponse
    {
        $filters = $request->filters();
        $rows = $invoiceQuery->build($filters)->lazy()->map(fn (FiscalInvoice $invoice): array => [
            $invoice->kora_reference,
            optional($invoice->reservation)->reference ?? optional($invoice->reservation)->uuid,
            $invoice->mecef_uid,
            $invoice->status->label(),
            $invoice->last_error,
            $invoice->created_at?->toIso8601String(),
        ]);
        $headers = ['Référence', 'Réservation', 'UID MECeF', 'Statut', 'Cause', 'Créée le'];
        $format = $request->string('format')->value() === 'xlsx' ? 'xlsx' : 'csv';
        activity('finance')
            ->causedBy($request->user())
            ->withProperties(['kind' => 'fiscal_invoices', 'format' => $format, 'filters' => $filters])
            ->log('finance_exported');

        return $format === 'xlsx'
            ? $exporter->xlsx('factures-fiscales.xlsx', $headers, $rows)
            : $exporter->csv('factures-fiscales.csv', $headers, $rows);
    }

    /** @return array<string, mixed> */
    private function serializeListItem(FiscalInvoice $invoice): array
    {
        return [
            'uuid' => $invoice->uuid,
            'reference' => $invoice->kora_reference,
            'reservationReference' => optional($invoice->reservation)->reference,
            'reservationUuid' => optional($invoice->reservation)->uuid,
            'mecefUid' => $invoice->mecef_uid,
            'status' => $invoice->status->value,
            'statusLabel' => $invoice->status->label(),
            'lastError' => $invoice->last_error,
            'amount' => (int) $invoice->total_with_tax,
            'currency' => optional($invoice->reservation)->currency ?? 'XOF',
            'attemptCount' => $invoice->syncAttempts->count(),
            'createdAt' => $invoice->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeDetail(FiscalInvoice $invoice, bool $canViewPayload): array
    {
        return array_merge($this->serializeListItem($invoice), [
            'attempts' => $invoice->syncAttempts->map(fn (FiscalSyncAttempt $attempt): array => [
                'uuid' => $attempt->uuid,
                'operation' => $attempt->operation,
                'number' => $attempt->attempt_number,
                'status' => $attempt->status,
                'responseCode' => $attempt->response_code,
                'errorCode' => $attempt->error_code,
                'errorMessage' => $attempt->error_message,
                'summary' => $attempt->response_payload,
                'payload' => $canViewPayload ? $attempt->response_payload_encrypted : null,
                'attemptedAt' => $attempt->attempted_at->toIso8601String(),
                'completedAt' => $attempt->completed_at?->toIso8601String(),
                'canViewPayload' => $canViewPayload,
            ])->values()->all(),
            'canViewPayload' => $canViewPayload,
        ]);
    }
}
