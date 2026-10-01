<?php

namespace App\Modules\Fiscalization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\FinanceExportService;
use App\Modules\Fiscalization\Actions\GenerateFiscalInvoicePdfAction;
use App\Modules\Fiscalization\Actions\InitiateCreditNoteAction;
use App\Modules\Fiscalization\Actions\RetryFiscalInvoiceAction;
use App\Modules\Fiscalization\Http\Requests\InitiateCreditNoteRequest;
use App\Modules\Fiscalization\Http\Requests\ListFiscalInvoicesRequest;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Services\FiscalInvoicePresenter;
use App\Modules\Fiscalization\Services\FiscalInvoiceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalInvoiceController extends Controller
{
    public function index(
        ListFiscalInvoicesRequest $request,
        FiscalInvoiceQuery $invoiceQuery,
        FiscalInvoicePresenter $presenter,
    ): Response {
        $filters = $request->filters();
        $invoices = $invoiceQuery->build($filters)->paginate(20)->withQueryString();
        $invoiceDetail = null;

        if ($request->filled('invoice')) {
            $invoice = $invoiceQuery->forUuid($request->string('invoice')->value());
            Gate::authorize('view', $invoice);
            $invoiceDetail = $presenter->detail($invoice, $request->user()->can('fiscalization.payload.view'));
        }

        return Inertia::render('fiscal-invoices', [
            'invoices' => $invoices->through(fn (FiscalInvoice $invoice): array => $presenter->listItem($invoice)),
            'filters' => $filters,
            'statuses' => $invoiceQuery->statuses(),
            'counters' => $invoiceQuery->counters($filters),
            'permissions' => [
                'canRetry' => $request->user()->can('fiscalization.retry'),
                'canCredit' => $request->user()->can('fiscalization.credit'),
            ],
            'invoiceDetail' => $invoiceDetail,
        ]);
    }

    public function show(
        FiscalInvoice $fiscalInvoice,
        GenerateFiscalInvoicePdfAction $pdf,
    ): SymfonyResponse {
        Gate::authorize('view', $fiscalInvoice);

        return response($pdf->renderInvoice(
            $fiscalInvoice,
            pdfUrl: route('fiscal-invoices.pdf', $fiscalInvoice->uuid),
            backUrl: route('fiscal-invoices.index'),
        ));
    }

    public function pdf(
        FiscalInvoice $fiscalInvoice,
        GenerateFiscalInvoicePdfAction $generatePdf,
    ): SymfonyResponse {
        Gate::authorize('view', $fiscalInvoice);
        $document = $generatePdf->execute($fiscalInvoice);

        return response($document['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document['filename'].'"',
        ]);
    }

    public function retry(FiscalInvoice $fiscalInvoice, RetryFiscalInvoiceAction $retry): RedirectResponse
    {
        Gate::authorize('retry', $fiscalInvoice);
        $retry->execute($fiscalInvoice, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('La relance fiscale a été mise en file.')]);

        return back();
    }

    public function credit(
        InitiateCreditNoteRequest $request,
        FiscalInvoice $fiscalInvoice,
        InitiateCreditNoteAction $initiateCreditNote,
    ): RedirectResponse {
        Gate::authorize('credit', $fiscalInvoice);

        try {
            $validated = $request->validated();
            $data = [
                'mode' => (string) ($validated['mode'] ?? 'full'),
                'reason' => (string) ($validated['reason'] ?? ''),
                'idempotency_key' => (string) ($validated['idempotency_key'] ?? ''),
                'lines' => array_values(array_filter($validated['lines'] ?? [], static fn (mixed $line): bool => is_array($line))),
            ];
            $initiateCreditNote->execute($fiscalInvoice, $data, $request->user());
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('La facture d’avoir a été mise en file pour émission.'),
        ]);

        return back();
    }

    public function export(
        ListFiscalInvoicesRequest $request,
        FiscalInvoiceQuery $invoiceQuery,
        FiscalInvoicePresenter $presenter,
        FinanceExportService $exporter,
    ): StreamedResponse {
        $filters = $request->filters();
        $rows = $invoiceQuery->build($filters)->lazy()->map(function (FiscalInvoice $invoice) use ($presenter): array {
            $item = $presenter->listItem($invoice);

            return [
                $item['reference'],
                $item['documentType'],
                $item['documentTypeLabel'],
                $item['koraReference'],
                $item['reservationReference'],
                $item['client']['name'],
                $item['client']['contact'],
                $item['amount'],
                $item['currency'],
                $item['statusLabel'],
                $item['mecefUid'],
                $item['createdAt'],
            ];
        });
        $headers = [
            'Référence fiscale',
            'Type',
            'Type détaillé',
            'Référence KORA',
            'Réservation',
            'Client',
            'Contact',
            'Montant',
            'Devise',
            'Statut',
            'UID e‑MECeF',
            'Créée le',
        ];
        $format = $request->string('format')->value() === 'xlsx' ? 'xlsx' : 'csv';
        activity('finance')
            ->causedBy($request->user())
            ->withProperties(['kind' => 'fiscal_invoices', 'format' => $format, 'filters' => $filters])
            ->log('finance_exported');

        return $format === 'xlsx'
            ? $exporter->xlsx('factures-fiscales.xlsx', $headers, $rows)
            : $exporter->csv('factures-fiscales.csv', $headers, $rows);
    }
}
