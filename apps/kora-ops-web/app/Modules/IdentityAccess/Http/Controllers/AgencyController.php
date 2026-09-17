<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Cash\Actions\ChangeCashRegisterStatusAction;
use App\Modules\Cash\Actions\CreateCashRegisterAction;
use App\Modules\Cash\Actions\EnsureAgencyCashRegistersAction;
use App\Modules\Cash\Actions\UpdateCashRegisterAction;
use App\Modules\Cash\Data\CashRegisterStatus;
use App\Modules\Cash\Http\Requests\ChangeCashRegisterStatusRequest;
use App\Modules\Cash\Http\Requests\StoreCashRegisterRequest;
use App\Modules\Cash\Http\Requests\UpdateCashRegisterRequest;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Services\CashRegisterBalance;
use App\Modules\IdentityAccess\Actions\ChangeAgencyStatusAction;
use App\Modules\IdentityAccess\Actions\CreateAgencyAction;
use App\Modules\IdentityAccess\Actions\UpdateAgencyAction;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Http\Requests\ChangeAgencyStatusRequest;
use App\Modules\IdentityAccess\Http\Requests\StoreAgencyRequest;
use App\Modules\IdentityAccess\Http\Requests\UpdateAgencyRequest;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AgencyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Agency::class);

        $query = Agency::query()
            ->with(['head', 'paymentAccounts'])
            ->orderBy('name');

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(city) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(country) LIKE ?', [$term]);
            });
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, array_column(AgencyStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $agencies = $query->paginate(15)->through(fn (Agency $agency): array => $this->serializeAgency($agency));

        $headCandidates = User::query()
            ->role([SystemRole::AgencyManager->value, SystemRole::AgencyAgent->value])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values();

        return inertia('agencies', [
            'agencies' => $agencies,
            'statuses' => AgencyStatus::options(),
            'headCandidates' => $headCandidates,
            'permissions' => [
                'canCreate' => Gate::allows('create', Agency::class),
                'canUpdate' => Gate::allows('update', new Agency),
                'canDeactivate' => Gate::allows('deactivate', new Agency),
            ],
        ]);
    }

    public function show(Request $request, Agency $agency, EnsureAgencyCashRegistersAction $ensureVault, CashRegisterBalance $balances): Response
    {
        Gate::authorize('view', $agency);
        abort_unless($request->user()?->can('cash.view'), 403);

        $ensureVault->execute($agency);
        $agency->load(['head', 'paymentAccounts', 'cashRegisters.authorizedUsers']);

        return Inertia::render('agencies/detail', [
            'agency' => [
                'uuid' => $agency->uuid,
                'code' => $agency->code,
                'name' => $agency->name,
                'city' => $agency->city,
                'country' => $agency->country,
                'head' => $agency->head ? ['uuid' => $agency->head->uuid, 'name' => $agency->head->name] : null,
                'status' => AgencyStatus::from((string) $agency->getRawOriginal('status'))->value,
            ],
            'cashSummary' => $balances->agencyTotals($agency->getKey()),
            'cashRegisters' => $agency->cashRegisters
                ->sortBy(fn (CashRegister $register): string => $register->isVault() ? '0' : '1'.$register->code)
                ->values()
                ->map(fn (CashRegister $register): array => $this->serializeCashRegister($register, $balances))
                ->all(),
            'paymentAccounts' => $agency->paymentAccounts->map(fn (AgencyPaymentAccount $account): array => [
                'uuid' => $account->uuid,
                'network' => $account->network->value,
                'label' => $account->label,
                'accountIdentifier' => $account->account_identifier,
                'status' => $account->getRawOriginal('status'),
                'balance' => $balances->mobileMoneyPublished($account),
            ])->values()->all(),
            'agentCandidates' => User::query()
                ->where('agency_id', $agency->getKey())
                ->role([SystemRole::AgencyManager->value, SystemRole::AgencyAgent->value])
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => ['uuid' => $user->uuid, 'name' => $user->name])
                ->values()
                ->all(),
            'permissions' => [
                'canManage' => Gate::allows('update', $agency),
                'canViewCash' => $request->user()->can('cash.view'),
                'canCreateTransfer' => $request->user()->can('cash.transfer.create'),
                'canApproveTransfer' => $request->user()->can('cash.transfer.approve'),
            ],
        ]);
    }

    public function storeCashRegister(
        StoreCashRegisterRequest $request,
        Agency $agency,
        CreateCashRegisterAction $createCashRegister,
    ): RedirectResponse {
        $createCashRegister->execute(
            $agency,
            (string) $request->validated('label'),
            array_values((array) $request->validated('authorized_user_uuids', [])),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Le guichet a été créé.')]);

        return back();
    }

    public function updateCashRegister(
        UpdateCashRegisterRequest $request,
        CashRegister $cashRegister,
        UpdateCashRegisterAction $updateCashRegister,
    ): RedirectResponse {
        $updateCashRegister->execute(
            $cashRegister,
            (string) $request->validated('label'),
            array_values((array) $request->validated('authorized_user_uuids', [])),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('La caisse a été mise à jour.')]);

        return back();
    }

    public function changeCashRegisterStatus(
        ChangeCashRegisterStatusRequest $request,
        CashRegister $cashRegister,
        ChangeCashRegisterStatusAction $changeStatus,
    ): RedirectResponse {
        $changeStatus->execute($cashRegister, CashRegisterStatus::from((string) $request->validated('status')));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Le statut de la caisse a été mis à jour.')]);

        return back();
    }

    public function store(StoreAgencyRequest $request, CreateAgencyAction $createAgency): RedirectResponse
    {
        $createAgency->execute($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agence créée avec succès.')]);

        return to_route('agencies.index');
    }

    public function update(
        UpdateAgencyRequest $request,
        Agency $agency,
        UpdateAgencyAction $updateAgency,
    ): RedirectResponse {
        $updateAgency->execute($agency, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agence mise à jour avec succès.')]);

        return to_route('agencies.index');
    }

    public function changeStatus(
        ChangeAgencyStatusRequest $request,
        Agency $agency,
        ChangeAgencyStatusAction $changeAgencyStatus,
    ): RedirectResponse {
        $changeAgencyStatus->execute($agency, $request->status());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statut de l’agence mis à jour.')]);

        return to_route('agencies.index');
    }

    /** @return array<string, mixed> */
    private function serializeAgency(Agency $agency): array
    {
        return [
            'uuid' => $agency->uuid,
            'code' => $agency->code,
            'name' => $agency->name,
            'city' => $agency->city,
            'country' => $agency->country,
            'headUserId' => $agency->head_user_id,
            'headName' => $agency->head?->name,
            'status' => AgencyStatus::from((string) $agency->getRawOriginal('status'))->value,
            'createdAt' => $agency->created_at?->toISOString(),
            'updatedAt' => $agency->updated_at?->toISOString(),
            'paymentAccounts' => $agency->paymentAccounts->map(fn (AgencyPaymentAccount $account): array => [
                'uuid' => $account->uuid,
                'network' => $account->network->value,
                'label' => $account->label,
                'accountIdentifier' => $account->account_identifier,
                'status' => $account->getRawOriginal('status'),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeCashRegister(CashRegister $register, CashRegisterBalance $balances): array
    {
        $lastSession = $register->sessions()->with('agent')->latest('id')->first();
        $lastMovement = $register->movements()->latest('occurred_at')->latest('id')->first();

        return [
            'uuid' => $register->uuid,
            'code' => $register->code,
            'label' => $register->label,
            'kind' => $register->kind->value,
            'status' => $register->status->value,
            'publishedBalance' => $balances->published($register),
            'reservedAmount' => $balances->reserved($register),
            'availableBalance' => $balances->available($register),
            'lastSession' => $lastSession ? [
                'uuid' => $lastSession->uuid,
                'status' => $lastSession->status->value,
                'agentName' => $lastSession->agent?->name,
                'businessDate' => $lastSession->business_date->toDateString(),
            ] : null,
            'lastMovementAt' => $lastMovement?->occurred_at?->toISOString(),
            'authorizedUsers' => $register->authorizedUsers->map(fn (User $user): array => [
                'uuid' => $user->uuid,
                'name' => $user->name,
            ])->values()->all(),
        ];
    }
}
