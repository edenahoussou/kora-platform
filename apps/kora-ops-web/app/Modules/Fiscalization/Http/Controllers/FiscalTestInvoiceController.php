<?php

namespace App\Modules\Fiscalization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\RunFiscalTestInvoice;
use App\Modules\Fiscalization\Actions\GenerateFiscalTestInvoicePdfAction;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Http\Requests\StoreFiscalTestInvoiceRunRequest;
use App\Modules\Fiscalization\Models\FiscalTestInvoiceRun;
use App\Modules\Fiscalization\Services\FiscalTestCaseCatalog;
use App\Modules\Fiscalization\Services\FiscalTestInvoicePresenter;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FiscalTestInvoiceController extends Controller
{
    public function index(
        FiscalTestCaseCatalog $catalog,
        FiscalTestInvoicePresenter $presenter,
        IntegrationsSettings $settings,
    ): Response {
        Gate::authorize('manage-fiscal-test-invoices');

        $runs = FiscalTestInvoiceRun::query()
            ->with(['emecfInvoice.items', 'emecfInvoice.payments', 'requestedBy'])
            ->latest('id')
            ->get()
            ->groupBy('case_number');

        $cases = collect($catalog->cases())->map(function (array $case) use ($runs, $presenter): array {
            $caseRuns = $runs->get($case['number'], collect());
            $latestRun = $caseRuns->first();
            $sourceReady = $case['number'] !== 6 || FiscalTestInvoiceRun::query()
                ->where('case_number', 5)
                ->where('status', FiscalInvoiceStatus::Certified->value)
                ->exists();

            return array_merge($case, [
                'canRun' => $sourceReady,
                'latestRun' => $latestRun === null ? null : $presenter->listItem($latestRun),
                'runs' => $caseRuns->map(fn (FiscalTestInvoiceRun $run): array => [
                    'summary' => $presenter->listItem($run),
                    'detail' => $presenter->detail($run),
                ])->values()->all(),
                'historyCount' => $caseRuns->count(),
            ]);
        })->values()->all();

        return Inertia::render('fiscal-test-invoices', [
            'cases' => $cases,
            'testModeEnabled' => $settings->emecfTestMode,
            'configured' => filled($settings->emecfToken)
                && filled($settings->emecfDefaultIfu)
                && $settings->emecfDefaultIfu !== '0000000000000',
        ]);
    }

    public function store(
        StoreFiscalTestInvoiceRunRequest $request,
        FiscalTestCaseCatalog $catalog,
        IntegrationsSettings $settings,
    ): RedirectResponse {
        Gate::authorize('manage-fiscal-test-invoices');

        abort_unless($settings->emecfTestMode, 409, 'Les émissions de test sont désactivées tant que e‑MECeF n’est pas configuré en mode TEST.');
        abort_unless(filled($settings->emecfToken) && $settings->emecfDefaultIfu !== '0000000000000', 409, 'Le token TEST et l’IFU de l’émetteur doivent être configurés dans les paramètres e‑MECeF.');

        $validated = $request->validated();
        $caseNumber = (int) $validated['caseNumber'];
        $sourceRun = null;
        if ($caseNumber === 6) {
            $sourceRun = FiscalTestInvoiceRun::query()
                ->with('emecfInvoice.items')
                ->where('case_number', 5)
                ->where('status', FiscalInvoiceStatus::Certified->value)
                ->latest('id')
                ->first();

            abort_if($sourceRun === null, 422, 'Le test 6 nécessite une facture certifiée pour le test 5.');
        }

        $run = FiscalTestInvoiceRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'case_number' => $caseNumber,
            'requested_by_user_id' => $request->user()->getKey(),
            'source_run_id' => $sourceRun?->getKey(),
            'status' => FiscalInvoiceStatus::Pending->value,
            'request_snapshot' => $catalog->payload($caseNumber, $settings, $request->user(), $validated['clientIfu'] ?? null),
        ]);

        RunFiscalTestInvoice::dispatch($run->getKey());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('La facture de test a été mise en file.')]);

        return back();
    }

    public function pdf(FiscalTestInvoiceRun $run, GenerateFiscalTestInvoicePdfAction $generatePdf): SymfonyResponse
    {
        Gate::authorize('manage-fiscal-test-invoices');
        abort_unless($run->status === FiscalInvoiceStatus::Certified->value, 409, 'Le PDF est disponible après certification de la facture de test.');

        $document = $generatePdf->execute($run);

        return response($document['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document['filename'].'"',
        ]);
    }
}
