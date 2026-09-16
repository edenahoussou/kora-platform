<?php

namespace App\Modules\Cash\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Cash\Actions\ApproveCashMovementAction;
use App\Modules\Cash\Actions\ApproveCashTransferAction;
use App\Modules\Cash\Actions\ApproveCashVarianceAction;
use App\Modules\Cash\Actions\CloseCashSessionAction;
use App\Modules\Cash\Actions\CreateCashTransferAction;
use App\Modules\Cash\Actions\OpenCashSessionAction;
use App\Modules\Cash\Actions\RecordCashMovementAction;
use App\Modules\Cash\Actions\RejectCashTransferAction;
use App\Modules\Cash\Http\Requests\ApproveCashMovementRequest;
use App\Modules\Cash\Http\Requests\ApproveCashTransferRequest;
use App\Modules\Cash\Http\Requests\ApproveCashVarianceRequest;
use App\Modules\Cash\Http\Requests\CloseCashSessionRequest;
use App\Modules\Cash\Http\Requests\ListCashMovementsRequest;
use App\Modules\Cash\Http\Requests\StoreCashMovementRequest;
use App\Modules\Cash\Http\Requests\StoreCashSessionRequest;
use App\Modules\Cash\Http\Requests\StoreCashTransferRequest;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Models\CashTransfer;
use App\Modules\Cash\Services\CashJournalQuery;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashSessionController extends Controller
{
    public function index(ListCashMovementsRequest $request, CashJournalQuery $journal): Response
    {
        $user = $request->user();
        $session = $journal->session($user);
        $movements = $journal->movements($session, $request->filters())
            ->with(['cashLedger'])->latest('id')->paginate(8)->withQueryString();

        return Inertia::render('cash', [
            'cashSession' => $this->serializeSession($session),
            'movements' => $movements->through(fn (CashMovement $movement): array => $this->serializeMovement($movement)),
            'filters' => $request->filters(),
            'mobileMoneyAccounts' => $this->serializeAccounts($user->agency_id),
            'cashRegisters' => $this->serializeRegisters($user),
            'permissions' => [
                'canOpen' => Gate::allows('create', CashSession::class),
                'canClose' => $session ? Gate::allows('close', $session) : false,
                'canApproveVariance' => $session ? Gate::allows('approveVariance', $session) : false,
                'canCreateMovement' => $user->can('cash.transfer.create'),
                'canApproveMovement' => $user->can('cash.transfer.approve'),
            ],
        ]);
    }

    public function export(ListCashMovementsRequest $request, CashJournalQuery $journal): StreamedResponse
    {
        $session = $journal->session($request->user());
        $query = $journal->movements($session, $request->filters())->with('cashLedger.agencyPaymentAccount');

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Date', 'Nature', 'Canal', 'Référence', 'Sens', 'Statut', 'Montant'], ';', '"', '');
            foreach ($query->lazyByIdDesc(500) as $movement) {
                $ledger = $movement->cashLedger;
                $channel = $ledger?->kind->value === 'cash' ? 'cash' : $ledger?->agencyPaymentAccount?->network->value;
                $cells = [
                    $movement->occurred_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '',
                    CashJournalQuery::TYPES[$movement->type] ?? $movement->type,
                    CashJournalQuery::CHANNELS[$channel ?? ''] ?? 'Mobile Money',
                    $movement->reference ?? '',
                    $movement->direction->value === 'in' ? 'Encaissement' : 'Sortie',
                    CashJournalQuery::STATUSES[$movement->status->value],
                    (string) $movement->amount,
                ];
                fputcsv($stream, array_map(fn (string $cell): string => preg_match('/^[\s]*[=+@-]/u', $cell) ? "'".$cell : $cell, $cells), ';', '"', '');
            }
            fclose($stream);
        }, 'encaissements-'.($session?->business_date->toDateString() ?? now()->toDateString()).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(
        StoreCashSessionRequest $request,
        OpenCashSessionAction $openCashSession,
    ): RedirectResponse {
        $openCashSession->execute($request->user(), $request->ledgerData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Caisse ouverte. Vous pouvez confirmer le paiement.'),
        ]);

        return back();
    }

    public function close(
        CloseCashSessionRequest $request,
        CashSession $cashSession,
        CloseCashSessionAction $closeCashSession,
    ): RedirectResponse {
        $closeCashSession->execute($cashSession, $request->user(), $request->ledgerData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('La clôture de caisse a été enregistrée.'),
        ]);

        return back();
    }

    public function approveVariance(
        ApproveCashVarianceRequest $request,
        CashSession $cashSession,
        ApproveCashVarianceAction $approveCashVariance,
    ): RedirectResponse {
        $approveCashVariance->execute($cashSession, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('L’écart de caisse a été approuvé.'),
        ]);

        return back();
    }

    public function storeMovement(
        StoreCashMovementRequest $request,
        RecordCashMovementAction $recordCashMovement,
    ): RedirectResponse {
        $recordCashMovement->execute($request->user(), $request->movementData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Le reversement est en attente de validation.'),
        ]);

        return back();
    }

    public function approveMovement(
        ApproveCashMovementRequest $request,
        CashMovement $movement,
        ApproveCashMovementAction $approveCashMovement,
    ): RedirectResponse {
        $approveCashMovement->execute($movement, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Le mouvement a été approuvé.'),
        ]);

        return back();
    }

    public function rejectMovement(
        ApproveCashMovementRequest $request,
        CashMovement $movement,
        ApproveCashMovementAction $approveCashMovement,
    ): RedirectResponse {
        $reason = (string) $request->validated('reason');

        if ($reason === '') {
            return back()->withErrors(['reason' => 'Le motif du rejet est obligatoire.']);
        }

        $approveCashMovement->reject($movement, $request->user(), $reason);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Le mouvement a été rejeté.'),
        ]);

        return back();
    }

    public function storeTransfer(
        StoreCashTransferRequest $request,
        CreateCashTransferAction $createCashTransfer,
    ): RedirectResponse {
        $createCashTransfer->execute($request->user(), $request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Le transfert est en attente de validation.'),
        ]);

        return back();
    }

    public function approveTransfer(
        ApproveCashTransferRequest $request,
        CashTransfer $cashTransfer,
        ApproveCashTransferAction $approveCashTransfer,
    ): RedirectResponse {
        $approveCashTransfer->execute($cashTransfer, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Le transfert a été publié dans le journal.'),
        ]);

        return back();
    }

    public function rejectTransfer(
        ApproveCashTransferRequest $request,
        CashTransfer $cashTransfer,
        RejectCashTransferAction $rejectCashTransfer,
    ): RedirectResponse {
        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return back()->withErrors(['reason' => 'Le motif du rejet est obligatoire.']);
        }

        $rejectCashTransfer->execute($cashTransfer, $request->user(), $reason);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Le transfert a été rejeté.'),
        ]);

        return back();
    }

    /** @return array<string, mixed>|null  */
    private function serializeSession(?CashSession $session): ?array
    {
        if ($session === null) {
            return null;
        }

        return [
            'uuid' => $session->uuid,
            'isOpen' => $session->status->value === 'open',
            'canOpen' => false,
            'totalCash' => (int) $session->total_cash,
            'totalMobileMoney' => (int) $session->total_mobile_money,
            'businessDate' => $session->business_date->toDateString(),
            'status' => $session->status->value,
            'openedAt' => $session->opened_at?->toISOString(),
            'agentName' => $session->agent?->name,
            'agencyName' => $session->agency?->name,
            'cashRegister' => $session->cashRegister ? [
                'uuid' => $session->cashRegister->uuid,
                'code' => $session->cashRegister->code,
                'label' => $session->cashRegister->label,
            ] : null,
            'ledgers' => $session->ledgers->map(fn ($ledger): array => [
                'uuid' => $ledger->uuid,
                'kind' => $ledger->kind->value,
                'status' => $ledger->status->value,
                'openingBalance' => (int) $ledger->opening_balance,
                'inflows' => (int) $ledger->inflows,
                'outflows' => (int) $ledger->outflows,
                'movementCount' => (int) $ledger->movements_count,
                'expectedBalance' => (int) $ledger->expected_balance,
                'actualBalance' => $ledger->actual_balance,
                'variance' => $ledger->variance,
                'account' => $ledger->agencyPaymentAccount ? [
                    'uuid' => $ledger->agencyPaymentAccount->uuid,
                    'network' => $ledger->agencyPaymentAccount->network->value,
                    'label' => $ledger->agencyPaymentAccount->label,
                    'accountIdentifier' => $this->maskAccountIdentifier($ledger->agencyPaymentAccount->account_identifier),
                ] : null,
                'media' => $this->serializeLedgerMedia($ledger),
            ])->values()->all(),

        ];
    }

    /** @return list<array<string, mixed>> */
    private function serializeAccounts(?int $agencyId): array
    {
        if ($agencyId === null) {
            return [];
        }

        return array_values(AgencyPaymentAccount::query()
            ->where('agency_id', $agencyId)
            ->where('status', 'active')
            ->orderBy('network')
            ->orderBy('label')
            ->get()
            ->map(fn (AgencyPaymentAccount $account): array => [
                'uuid' => $account->uuid,
                'network' => $account->network->value,
                'label' => $account->label,
                'accountIdentifier' => $this->maskAccountIdentifier($account->account_identifier),
            ])->values()->all());
    }

    /** @return array<int, array{uuid: string, code: string, label: string}> */
    private function serializeRegisters(User $user): array
    {
        if ($user->agency_id === null) {
            return [];
        }

        return CashRegister::query()
            ->where('agency_id', $user->agency_id)
            ->where('kind', 'counter')
            ->where('status', 'active')
            ->where(function ($query) use ($user): void {
                $query->whereDoesntHave('authorizedUsers')
                    ->orWhereHas('authorizedUsers', fn ($users) => $users->whereKey($user->getKey()));
            })
            ->orderBy('code')
            ->get()
            ->map(fn (CashRegister $register): array => [
                'uuid' => $register->uuid,
                'code' => $register->code,
                'label' => $register->label,
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function serializeLedgerMedia(CashLedger $ledger): array
    {
        return array_values($ledger->getMedia('opening-evidence')->merge($ledger->getMedia('closing-evidence'))
            ->map(fn (Media $media): array => $this->serializeMedia($media))->values()->all());
    }

    /** @return array<string, mixed> */
    private function serializeMovement(CashMovement $movement): array
    {
        return [
            'uuid' => $movement->uuid,
            'ledgerUuid' => $movement->cashLedger?->uuid,
            'type' => $movement->type,
            'direction' => $movement->direction->value,
            'status' => $movement->status->value,
            'amount' => (int) $movement->amount,
            'reference' => $movement->reference,
            'destinationLabel' => $movement->destination_label,
            'occurredAt' => $movement->occurred_at?->toISOString(),
            'media' => array_values($movement->getMedia('transfer-evidence')
                ->map(fn (Media $media): array => $this->serializeMedia($media))->values()->all()),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeMedia(Media $media): array
    {
        return [
            'uuid' => $media->uuid,
            'name' => $media->name,
            'mimeType' => $media->mime_type,
            'size' => (int) $media->size,
            'createdAt' => $media->created_at?->toISOString(),
            'url' => route('cash.media', ['mediaUuid' => $media->uuid]),
        ];
    }

    private function maskAccountIdentifier(string $identifier): string
    {
        $length = mb_strlen($identifier);

        return $length <= 4 ? $identifier : str_repeat('•', $length - 4).mb_substr($identifier, -4);
    }
}
