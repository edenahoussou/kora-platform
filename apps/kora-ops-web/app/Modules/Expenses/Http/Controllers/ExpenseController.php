<?php

namespace App\Modules\Expenses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Expenses\Actions\ApproveExpenseAction;
use App\Modules\Expenses\Actions\CreateExpenseAction;
use App\Modules\Expenses\Actions\RejectExpenseAction;
use App\Modules\Expenses\Actions\ReverseExpenseAction;
use App\Modules\Expenses\Http\Requests\ExpenseActionRequest;
use App\Modules\Expenses\Http\Requests\ListExpensesRequest;
use App\Modules\Expenses\Http\Requests\StoreExpenseRequest;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Services\ExpenseQuery;
use App\Modules\Finance\Services\FinanceExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(ListExpensesRequest $request, ExpenseQuery $expenseQuery): Response
    {
        $filters = $request->filters();
        $expenses = $expenseQuery->build($request->user(), $filters)->paginate(20)->withQueryString();
        $expenseDetail = null;
        if ($request->filled('expense')) {
            $detail = Expense::query()->with(['category', 'agency', 'creator', 'approver', 'cashLedger.agencyPaymentAccount', 'cashMovement', 'media'])->where('uuid', $request->string('expense')->value())->firstOrFail();
            Gate::authorize('view', $detail);
            $expenseDetail = $this->serializeDetail($detail, $request);
        }

        return Inertia::render('expenses', [
            'expenses' => $expenses->through(fn (Expense $expense): array => $this->serializeListItem($expense)),
            'filters' => $filters,
            'statuses' => $expenseQuery->statuses(),
            'channels' => $expenseQuery->channels(),
            'categories' => $expenseQuery->categories(),
            'ledgers' => CashLedger::query()->with('agencyPaymentAccount')->where('status', 'open')->whereHas('cashSession', fn ($query) => $query->where('agency_id', $request->user()->agency_id)->where('status', 'open'))->get()->map(fn (CashLedger $ledger): array => [
                'uuid' => $ledger->uuid,
                'label' => $ledger->kind->value === 'cash' ? 'Espèces' : ($ledger->agencyPaymentAccount->label ?? 'Mobile Money'),
            ])->values()->all(),
            'counters' => $expenseQuery->counters($request->user(), $filters),
            'permissions' => [
                'canCreate' => $request->user()->can('create', Expense::class),
                'canExport' => $request->user()->can('export', Expense::class),
            ],
            'expenseDetail' => $expenseDetail,
        ]);
    }

    public function show(Request $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('view', $expense);
        $expense->load(['category', 'agency', 'creator', 'approver', 'cashLedger.agencyPaymentAccount', 'cashMovement', 'media']);

        return to_route('expenses.index', ['expense' => $expense->uuid]);
    }

    public function store(StoreExpenseRequest $request, CreateExpenseAction $create): RedirectResponse
    {
        $expense = $create->execute($request->user(), $request->expenseData());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dépense enregistrée et envoyée pour validation.')]);

        return to_route('expenses.index', ['expense' => $expense->uuid]);
    }

    public function approve(ExpenseActionRequest $request, Expense $expense, ApproveExpenseAction $approve): RedirectResponse
    {
        Gate::authorize('approve', $expense);
        $approve->execute($expense, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dépense approuvée.')]);

        return back();
    }

    public function reject(ExpenseActionRequest $request, Expense $expense, RejectExpenseAction $reject): RedirectResponse
    {
        Gate::authorize('approve', $expense);
        $reject->execute($expense, $request->user(), (string) $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dépense rejetée.')]);

        return back();
    }

    public function reverse(ExpenseActionRequest $request, Expense $expense, ReverseExpenseAction $reverse): RedirectResponse
    {
        Gate::authorize('reverse', $expense);
        $reverse->execute($expense, $request->user(), (string) $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contrepassation enregistrée.')]);

        return back();
    }

    public function export(ListExpensesRequest $request, ExpenseQuery $expenseQuery, FinanceExportService $exporter): StreamedResponse
    {
        Gate::authorize('export', Expense::class);
        $filters = $request->filters();
        $rows = $expenseQuery->build($request->user(), $filters)->lazy()->map(fn (Expense $expense): array => [
            $expense->reference,
            $expense->agency?->name,
            $expense->category?->label,
            $expense->payment_channel->label(),
            $expense->amount,
            $expense->currency,
            $expense->status->label(),
            $expense->payee_label,
            $expense->occurred_at->toIso8601String(),
        ]);
        $headers = ['Référence', 'Agence', 'Catégorie', 'Moyen', 'Montant', 'Devise', 'Statut', 'Bénéficiaire', 'Date'];
        $format = $request->string('format')->value() === 'xlsx' ? 'xlsx' : 'csv';
        activity('finance')
            ->causedBy($request->user())
            ->withProperties(['kind' => 'expenses', 'format' => $format, 'filters' => $filters])
            ->log('finance_exported');

        return $format === 'xlsx'
            ? $exporter->xlsx('depenses-sorties.xlsx', $headers, $rows)
            : $exporter->csv('depenses-sorties.csv', $headers, $rows);
    }

    public function media(Request $request, Expense $expense, string $mediaUuid): mixed
    {
        Gate::authorize('view', $expense);
        $media = Media::query()->where('uuid', $mediaUuid)->where('model_type', Expense::class)->where('model_id', $expense->getKey())->firstOrFail();

        return $media->toInlineResponse($request);
    }

    /** @return array<string, mixed> */
    private function serializeListItem(Expense $expense): array
    {
        return [
            'uuid' => $expense->uuid,
            'reference' => $expense->reference,
            'agency' => $expense->agency?->name,
            'category' => $expense->category?->label,
            'categoryUuid' => $expense->category?->uuid,
            'paymentChannel' => $expense->payment_channel->value,
            'paymentChannelLabel' => $expense->payment_channel->label(),
            'amount' => (int) $expense->amount,
            'currency' => $expense->currency,
            'status' => $expense->status->value,
            'statusLabel' => $expense->status->label(),
            'payeeLabel' => $expense->payee_label,
            'occurredAt' => $expense->occurred_at->toIso8601String(),
            'hasEvidence' => $expense->hasMedia('evidence'),
            'isReconciled' => $expense->cash_ledger_id !== null,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeDetail(Expense $expense, Request $request): array
    {
        return array_merge($this->serializeListItem($expense), [
            'description' => $expense->description,
            'payeeReference' => $expense->payee_reference,
            'createdBy' => $expense->creator?->name,
            'approvedBy' => $expense->approver?->name,
            'rejectionReason' => $expense->rejection_reason,
            'reversalReason' => $expense->reversal_reason,
            'media' => $expense->getMedia('evidence')->map(fn (Media $media): array => [
                'uuid' => $media->uuid,
                'name' => $media->name,
                'mimeType' => $media->mime_type,
                'url' => route('expenses.media', [$expense->uuid, $media->uuid]),
            ])->values()->all(),
            'permissions' => [
                'canApprove' => $request->user()->can('approve', $expense),
                'canReverse' => $request->user()->can('reverse', $expense),
            ],
        ]);
    }
}
