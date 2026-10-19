<?php

namespace App\Modules\Cash\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Cash\Actions\ApproveCashMovementAction;
use App\Modules\Cash\Actions\ApproveCashTransferAction;
use App\Modules\Cash\Actions\ApproveCashVarianceAction;
use App\Modules\Cash\Actions\CloseCashSessionAction;
use App\Modules\Cash\Actions\CreateCashTransferAction;
use App\Modules\Cash\Actions\ForceCloseCashSessionAction;
use App\Modules\Cash\Actions\OpenCashSessionAction;
use App\Modules\Cash\Actions\RecordCashMovementAction;
use App\Modules\Cash\Actions\RejectCashTransferAction;
use App\Modules\Cash\Http\Requests\ApproveCashMovementRequest;
use App\Modules\Cash\Http\Requests\ApproveCashTransferRequest;
use App\Modules\Cash\Http\Requests\ApproveCashVarianceRequest;
use App\Modules\Cash\Http\Requests\CloseCashSessionRequest;
use App\Modules\Cash\Http\Requests\ForceCloseCashSessionRequest;
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
use App\Modules\Cash\Services\CashRegisterBalance;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashSessionController extends Controller
{
    public function index(ListCashMovementsRequest $request, CashJournalQuery $journal, CashRegisterBalance $balances): Response
    {
        $user = $request->user();
        $session = $journal->session($user);
        $filters = $request->filters();
        $scopedSessions = $journal->journalScopedSessions($user, $filters);

        $operations = $journal->operations(
            $scopedSessions,
            $filters,
        )->paginate(15, ['*'], 'page')->withQueryString();

        $sessionDetailUuid = trim((string) $request->input('session_detail', ''));

        $sessionDetail = null;
        if ($sessionDetailUuid !== '') {
            $sessionDetail = function () use ($sessionDetailUuid, $journal, $user): ?array {
                $detailSession = CashSession::query()->where('uuid', $sessionDetailUuid)->first();
                if (! $detailSession) {
                    return null;
                }

                Gate::authorize('view', $detailSession);
                $journal->loadRelations($detailSession);

                return $this->serializeSessionDetail($detailSession, $user);
            };
        }

        return Inertia::render('cash', [
            'cashSession' => $this->serializeSession($session),
            'sessionDetail' => $sessionDetail,
            'operations' => $operations->through(fn (stdClass $row): array => $this->serializeOperation($row)),
            'filters' => $filters,
            'filterOptions' => [
                'agents' => ($user->can('cash.view-agency') || $user->can('cash.view-network'))
                    ? $this->filterAgents($user)
                    : [],
            ],
            'mobileMoneyAccounts' => $this->serializeAccounts($user->agency_id),
            'cashRegisters' => $this->serializeRegisters($user),
            'agencyBalances' => $this->serializeBalances($user, $balances),
            'permissions' => [
                'canOpen' => Gate::allows('create', CashSession::class),
                'canClose' => $session ? Gate::allows('close', $session) : false,
                'canApproveVariance' => $session ? Gate::allows('approveVariance', $session) : false,
                'canCreateMovement' => $user->can('cash.transfer.create'),
                'canApproveMovement' => $user->can('cash.transfer.approve'),
                'canViewAgency' => $user->can('cash.view-agency') || $user->can('cash.view-network'),
            ],
        ]);
    }

    public function forceClose(
        ForceCloseCashSessionRequest $request,
        CashSession $cashSession,
        ForceCloseCashSessionAction $forceCloseCashSession,
    ): RedirectResponse {
        $forceCloseCashSession->execute($cashSession, $request->user(), (string) $request->validated('reason'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('La session a été clôturée de force.'),
        ]);

        return back();
    }

    public function export(ListCashMovementsRequest $request, CashJournalQuery $journal): StreamedResponse
    {
        $filters = $request->filters();
        $user = $request->user();
        $sessions = $journal->journalScopedSessions($user, $filters);
        $query = $journal->movementsForSessions($sessions, $filters)->with('cashLedger.agencyPaymentAccount');

        $sessionUuid = $filters['session_uuid'];
        $dateSuffix = $sessionUuid !== '' ? $sessionUuid : (
            $filters['from'] !== '' ? $filters['from'] : now()->toDateString()
        );

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
        }, 'encaissements-'.$dateSuffix.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
            'isStale' => $session->status->value === 'open'
                && $session->business_date->toDateString() < now()->toDateString(),
            'isLateClosing' => $session->closed_at !== null
                && $session->closed_at->toDateString() > $session->business_date->toDateString(),
            'isForced' => $session->force_closed_at !== null,
            'canOpen' => false,
            'totalCash' => (int) $session->total_cash,
            'totalMobileMoney' => (int) $session->total_mobile_money,
            'businessDate' => $session->business_date->toDateString(),
            'status' => $session->status->value,
            'openedAt' => $session->opened_at?->toISOString(),
            'submittedAt' => $session->submitted_at?->toISOString(),
            'closedAt' => $session->closed_at?->toISOString(),
            'approvedAt' => $session->approved_at?->toISOString(),
            'approvedByName' => $session->approvedBy?->name,
            'forceClosedAt' => $session->force_closed_at?->toISOString(),
            'forceClosedByName' => $session->forceClosedBy?->name,
            'closingComment' => $session->closing_comment,
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
                'openingMedia' => $this->serializeLedgerMedia($ledger, 'opening-evidence'),
                'closingMedia' => $this->serializeLedgerMedia($ledger, 'closing-evidence'),
            ])->values()->all(),

        ];
    }

    /** @return array<string, mixed>|null */
    private function serializeSessionDetail(?CashSession $session, User $user): ?array
    {
        if ($session === null) {
            return null;
        }

        $serialized = $this->serializeSession($session);
        if ($serialized === null) {
            return null;
        }

        $serialized['permissions'] = [
            'canApproveVariance' => Gate::allows('approveVariance', $session),
            'canForceClose' => Gate::allows('forceClose', $session),
            'canApproveMovement' => $user->can('cash.transfer.approve'),
        ];

        return $serialized;
    }

    /**
     * Soldes théoriques persistants de l'agence, visibles même sans session
     * ouverte : dernier état déclaré + mouvements publiés par caisse et compte.
     *
     * @return array<string, mixed>|null
     */
    private function serializeBalances(User $user, CashRegisterBalance $balances): ?array
    {
        if ($user->agency_id === null) {
            return null;
        }

        $registers = CashRegister::query()
            ->where('agency_id', $user->agency_id)
            ->where('status', 'active')
            ->orderBy('code')
            ->get();
        $accounts = AgencyPaymentAccount::query()
            ->where('agency_id', $user->agency_id)
            ->orderBy('network')
            ->orderBy('label')
            ->get();

        $cash = $registers->sum(fn (CashRegister $register): int => $balances->published($register));
        $mobileMoney = $accounts->sum(fn (AgencyPaymentAccount $account): int => $balances->mobileMoneyPublished($account));

        return [
            'cash' => $cash,
            'mobileMoney' => $mobileMoney,
            'global' => $cash + $mobileMoney,
            'registers' => $registers->map(fn (CashRegister $register): array => [
                'uuid' => $register->uuid,
                'code' => $register->code,
                'label' => $register->label,
                'kind' => $register->kind->value,
                'published' => $balances->published($register),
                'reserved' => $balances->reserved($register),
                'available' => $balances->available($register),
            ])->values()->all(),
            'accounts' => $accounts->map(fn (AgencyPaymentAccount $account): array => [
                'uuid' => $account->uuid,
                'network' => $account->network->value,
                'label' => $account->label,
                'status' => $account->status,
                'accountIdentifier' => $this->maskAccountIdentifier($account->account_identifier),
                'balance' => $balances->mobileMoneyPublished($account),
            ])->values()->all(),
        ];
    }

    /** @return array<int, array{uuid: string, name: string}> */
    private function filterAgents(User $user): array
    {
        $query = User::query()->orderBy('name');

        if (! $user->can('cash.view-network') && $user->agency_id !== null) {
            $query->where('agency_id', $user->agency_id);
        }

        return array_values($query->get(['uuid', 'name'])
            ->map(fn (User $agent): array => ['uuid' => $agent->uuid, 'name' => $agent->name])
            ->all());
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
    private function serializeLedgerMedia(CashLedger $ledger, string $collection): array
    {
        return array_values($ledger->getMedia($collection)
            ->map(fn (Media $media): array => $this->serializeMedia($media))->values()->all());
    }

    /**
     * Ligne du journal unifié : mouvement financier ou événement de
     * cycle de vie (ouverture, soumission, clôture, approbation).
     *
     * @return array<string, mixed>
     */
    private function serializeOperation(stdClass $row): array
    {
        return [
            'key' => implode('-', [$row->kind, $row->uuid, $row->event]),
            'uuid' => $row->uuid,
            'kind' => $row->kind,
            'event' => $row->event,
            'direction' => $row->direction,
            'status' => $row->status,
            'amount' => $row->amount === null ? null : (int) $row->amount,
            'reference' => $row->reference,
            'occurredAt' => $row->occurred_at === null ? null : Carbon::parse($row->occurred_at)->toISOString(),
            'sessionUuid' => $row->session_uuid,
            'businessDate' => mb_substr((string) $row->business_date, 0, 10),
            'agentName' => $row->agent_name,
            'channel' => $row->channel,
            'destinationLabel' => $row->destination_label,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeMedia(Media $media): array
    {
        $isImage = str_starts_with((string) $media->mime_type, 'image/');

        return [
            'uuid' => $media->uuid,
            'name' => $media->name,
            'mimeType' => $media->mime_type,
            'size' => (int) $media->size,
            'createdAt' => $media->created_at?->toISOString(),
            'url' => route('cash.media', ['mediaUuid' => $media->uuid]),
            'thumbUrl' => $isImage ? route('cash.media', ['mediaUuid' => $media->uuid, 'conversion' => 'thumb']) : null,
            'previewUrl' => $isImage ? route('cash.media', ['mediaUuid' => $media->uuid, 'conversion' => 'preview']) : null,
        ];
    }

    private function maskAccountIdentifier(string $identifier): string
    {
        $length = mb_strlen($identifier);

        return $length <= 4 ? $identifier : str_repeat('•', $length - 4).mb_substr($identifier, -4);
    }
}
