<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeCompanyAccountStatusRequest;
use App\Http\Requests\InviteCompanyReferentRequest;
use App\Http\Requests\StoreCompanyAccountWebRequest;
use App\Http\Requests\UpdateCompanyAccountWebRequest;
use App\Models\User;
use App\Modules\CompanyAccounts\Data\CompanyAccountStatus;
use App\Modules\CompanyAccounts\Data\CompanyAccountType;
use App\Modules\CompanyAccounts\Data\CompanyCreditHoldStatus;
use App\Modules\CompanyAccounts\Data\CompanyCreditTransactionType;
use App\Modules\CompanyAccounts\Data\CompanyMemberStatus;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use App\Modules\CompanyAccounts\Models\CompanyCreditHold;
use App\Modules\CompanyAccounts\Models\CompanyCreditTransaction;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompanyAccountWebController extends Controller
{
    public function __construct(private readonly PassengerIdentityResolver $identityResolver) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CompanyAccount::class);

        $query = CompanyAccount::query()
            ->with(['members' => fn ($members) => $members
                ->where('status', CompanyMemberStatus::Active->value)
                ->with('user:id,uuid,name,email')])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('reference', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name');

        $accounts = $query->paginate(15)->withQueryString();
        $metrics = $this->creditMetrics($accounts->getCollection());
        $accounts->setCollection($accounts->getCollection()->map(
            fn (CompanyAccount $account): array => $this->serializeAccount($account, $metrics),
        )->values());

        $summaryIds = (clone $query)->reorder()->pluck('id');
        $summaryMetrics = $this->creditMetrics(CompanyAccount::query()->whereKey($summaryIds)->get());

        return Inertia::render('companies', [
            'accounts' => $accounts,
            'summary' => [
                'total' => $summaryIds->count(),
                'active' => CompanyAccount::query()->whereKey($summaryIds)->where('status', CompanyAccountStatus::Active->value)->count(),
                'withAvailableCredit' => collect($summaryMetrics)->filter(fn (array $metric): bool => $metric['availableCredit'] > 0)->count(),
            ],
            'types' => collect(CompanyAccountType::cases())->mapWithKeys(fn (CompanyAccountType $type): array => [$type->value => match ($type) {
                CompanyAccountType::Company => 'Entreprise',
                CompanyAccountType::Ngo => 'ONG',
                CompanyAccountType::Embassy => 'Ambassade',
            }])->all(),
            'statuses' => [
                CompanyAccountStatus::Active->value => 'Active',
                CompanyAccountStatus::Inactive->value => 'Inactive',
            ],
            'userCandidates' => $this->userCandidates(),
            'permissions' => [
                'canCreate' => $request->user()->can('company-accounts.manage'),
                'canUpdate' => $request->user()->can('company-accounts.manage'),
                'canManageMembers' => $request->user()->can('company-accounts.manage'),
                'canChangeStatus' => $request->user()->can('company-accounts.manage'),
                'canViewCredit' => $request->user()->can('company-credit.view'),
            ],
        ]);
    }

    public function store(StoreCompanyAccountWebRequest $request): RedirectResponse
    {
        $account = DB::transaction(fn (): CompanyAccount => CompanyAccount::query()->create($request->validated() + [
            'reference' => $this->generateReference(),
            'status' => CompanyAccountStatus::Active,
            'created_by' => $request->user()->getKey(),
            'updated_by' => $request->user()->getKey(),
        ]));
        activity()->performedOn($account)->causedBy($request->user())->log('company_account_created');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organisation créée avec succès.']);

        return to_route('companies.index');
    }

    public function update(UpdateCompanyAccountWebRequest $request, CompanyAccount $companyAccount): RedirectResponse
    {
        Gate::authorize('manage', CompanyAccount::class);
        $companyAccount->update($request->validated() + ['updated_by' => $request->user()->getKey()]);
        activity()->performedOn($companyAccount)->causedBy($request->user())->log('company_account_updated');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organisation mise à jour.']);

        return to_route('companies.index');
    }

    public function changeStatus(ChangeCompanyAccountStatusRequest $request, CompanyAccount $companyAccount): RedirectResponse
    {
        Gate::authorize('manage', CompanyAccount::class);
        $status = CompanyAccountStatus::from($request->string('status')->toString());
        $companyAccount->update(['status' => $status, 'updated_by' => $request->user()->getKey()]);
        activity()->performedOn($companyAccount)->causedBy($request->user())->log('company_account_status_changed');
        Inertia::flash('toast', ['type' => 'success', 'message' => $status === CompanyAccountStatus::Active ? 'Organisation réactivée.' : 'Organisation désactivée.']);

        return to_route('companies.index');
    }

    public function addMember(Request $request, CompanyAccount $companyAccount): RedirectResponse
    {
        Gate::authorize('manage', CompanyAccount::class);
        $data = $request->validate([
            'user_uuid' => ['required', 'uuid', 'exists:users,uuid'],
            'role' => ['required', 'in:referent,member'],
        ]);
        $user = User::query()->where('uuid', $data['user_uuid'])->firstOrFail();
        $this->ensureEligibleMember($user);

        $member = DB::transaction(function () use ($companyAccount, $user, $data): CompanyAccountMember {
            $lockedAccount = CompanyAccount::query()->whereKey($companyAccount->getKey())->lockForUpdate()->firstOrFail();
            if ($data['role'] === 'referent') {
                $lockedAccount->members()
                    ->where('role', 'referent')
                    ->where('status', CompanyMemberStatus::Active->value)
                    ->where('user_id', '!=', $user->getKey())
                    ->update(['status' => CompanyMemberStatus::Inactive]);
            }

            return CompanyAccountMember::query()->updateOrCreate(
                ['company_account_id' => $lockedAccount->getKey(), 'user_id' => $user->getKey()],
                ['role' => $data['role'], 'status' => CompanyMemberStatus::Active],
            );
        });

        activity()->performedOn($companyAccount)->causedBy($request->user())->withProperties(['member_uuid' => $user->uuid, 'role' => $member->role->value])->log('company_account_member_updated');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Membre mis à jour.']);

        return to_route('companies.index');
    }

    /**
     * Invite un référent d'organisation : crée ou rattache le compte
     * utilisateur (voyageur) puis lui envoie un lien d'activation par email
     * pour définir son mot de passe.
     */
    public function inviteReferent(InviteCompanyReferentRequest $request, CompanyAccount $companyAccount): RedirectResponse
    {
        $data = $request->validated();
        $email = Str::lower($data['email']);

        $user = User::query()->where('email', $email)->first();
        $normalizedPhone = null;
        if ($user === null) {
            $normalizedPhone = $this->identityResolver->normalizePhone(
                $data['phone'] ?? null,
                $data['country_code'] ?? null,
            );
            if ($normalizedPhone !== null) {
                $user = User::query()->where('phone', $normalizedPhone)->first();
            }
        }

        if ($user === null) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $email,
                'phone' => $normalizedPhone ?? null,
                'country_code' => $data['country_code'] ?? null,
                'password' => Str::random(64),
                'status' => UserStatus::Active,
            ]);
            $user->assignRole(Role::findOrCreate(SystemRole::Passenger->value, 'web'));
        } else {
            $this->ensureEligibleMember($user);
        }

        DB::transaction(function () use ($companyAccount, $user): void {
            $lockedAccount = CompanyAccount::query()->whereKey($companyAccount->getKey())->lockForUpdate()->firstOrFail();
            $lockedAccount->members()
                ->where('role', 'referent')
                ->where('status', CompanyMemberStatus::Active->value)
                ->where('user_id', '!=', $user->getKey())
                ->update(['status' => CompanyMemberStatus::Inactive]);

            CompanyAccountMember::query()->updateOrCreate(
                ['company_account_id' => $lockedAccount->getKey(), 'user_id' => $user->getKey()],
                ['role' => 'referent', 'status' => CompanyMemberStatus::Active],
            );
        });

        Password::sendResetLink(['email' => $user->email]);

        activity()->performedOn($companyAccount)->causedBy($request->user())->withProperties(['referent_uuid' => $user->uuid])->log('company_account_referent_invited');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitation envoyée au responsable du compte.']);

        return to_route('companies.index');
    }

    /**
     * @param  Collection<int, CompanyAccount>  $accounts
     * @return array<int|string, array<string, mixed>>
     */
    private function creditMetrics(Collection $accounts): array
    {
        $ids = $accounts->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $period = now()->format('Y-m');
        $debits = CompanyCreditTransaction::query()->whereIn('company_account_id', $ids)->where('period_key', $period)->where('type', CompanyCreditTransactionType::Debit->value)->selectRaw('company_account_id, SUM(amount) aggregate')->groupBy('company_account_id')->pluck('aggregate', 'company_account_id');
        $refunds = CompanyCreditTransaction::query()->whereIn('company_account_id', $ids)->where('period_key', $period)->where('type', CompanyCreditTransactionType::Refund->value)->selectRaw('company_account_id, SUM(amount) aggregate')->groupBy('company_account_id')->pluck('aggregate', 'company_account_id');
        $holds = CompanyCreditHold::query()->whereIn('company_account_id', $ids)->where('period_key', $period)->where('status', CompanyCreditHoldStatus::Reserved->value)->where('expires_at', '>', now())->selectRaw('company_account_id, SUM(amount) aggregate')->groupBy('company_account_id')->pluck('aggregate', 'company_account_id');
        $activity = CompanyCreditTransaction::query()->whereIn('company_account_id', $ids)->latest()->get()->groupBy('company_account_id');

        return $accounts->mapWithKeys(function (CompanyAccount $account) use ($debits, $refunds, $holds, $activity): array {
            $consumed = max(0, (int) ($debits[$account->getKey()] ?? 0) - (int) ($refunds[$account->getKey()] ?? 0));
            $reserved = (int) ($holds[$account->getKey()] ?? 0);

            return [$account->getKey() => [
                'consumedCredit' => $consumed,
                'reservedCredit' => $reserved,
                'availableCredit' => max(0, (int) $account->monthly_credit_limit - $consumed - $reserved),
                'activity' => $activity->get($account->getKey(), collect())->take(8)->map(fn (CompanyCreditTransaction $transaction): array => [
                    'uuid' => $transaction->uuid,
                    'type' => $transaction->type->value,
                    'amount' => (int) $transaction->amount,
                    'reference' => $transaction->reference,
                    'periodKey' => $transaction->period_key,
                    'createdAt' => $transaction->created_at?->toIso8601String(),
                ])->values()->all(),
            ]];
        })->all();
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $metrics
     * @return array<string, mixed>
     */
    private function serializeAccount(CompanyAccount $account, array $metrics): array
    {
        $referent = $account->members->firstWhere('role', 'referent');
        $metric = $metrics[$account->getKey()] ?? ['consumedCredit' => 0, 'reservedCredit' => 0, 'availableCredit' => 0, 'activity' => []];

        return [
            'uuid' => $account->uuid,
            'name' => $account->name,
            'type' => $account->type->value,
            'reference' => $account->reference,
            'phone' => $account->phone,
            'email' => $account->email,
            'address' => $account->address,
            'city' => $account->city,
            'country' => $account->country,
            'ifu' => $account->ifu,
            'rccm' => $account->rccm,
            'status' => $account->status->value,
            'monthlyCreditLimit' => (int) $account->monthly_credit_limit,
            'consumedCredit' => $metric['consumedCredit'],
            'reservedCredit' => $metric['reservedCredit'],
            'availableCredit' => $metric['availableCredit'],
            'referent' => $referent?->user ? ['uuid' => $referent->user->uuid, 'name' => $referent->user->name, 'email' => $referent->user->email] : null,
            'members' => $account->members->map(fn (CompanyAccountMember $member): array => [
                'uuid' => $member->uuid,
                'userUuid' => $member->user->uuid,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role->value,
            ])->values()->all(),
            'creditActivity' => $metric['activity'],
        ];
    }

    /** @return list<array{uuid: string, name: string, email: string|null}> */
    private function userCandidates(): array
    {
        return array_values(User::query()
            ->where('status', UserStatus::Active->value)
            ->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', self::internalRoleValues()))
            ->orderBy('name')
            ->get(['uuid', 'name', 'email'])
            ->map(fn (User $user): array => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
            ])->all());
    }

    private function ensureEligibleMember(User $user): void
    {
        if ($user->status !== UserStatus::Active || $user->hasAnyRole(self::internalRoleValues())) {
            throw ValidationException::withMessages([
                'user_uuid' => 'Seuls les utilisateurs voyageurs actifs peuvent être rattachés à un compte organisationnel.',
            ]);
        }
    }

    /** @return list<string> */
    private static function internalRoleValues(): array
    {
        return array_map(fn (SystemRole $role): string => $role->value, SystemRole::internalRoles());
    }

    private function generateReference(): string
    {
        do {
            $reference = 'ORG-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (CompanyAccount::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
