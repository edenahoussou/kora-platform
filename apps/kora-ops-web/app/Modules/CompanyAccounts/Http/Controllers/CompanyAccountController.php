<?php

namespace App\Modules\CompanyAccounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CompanyAccounts\Data\CompanyAccountStatus;
use App\Modules\CompanyAccounts\Data\CompanyMemberStatus;
use App\Modules\CompanyAccounts\Http\Requests\StoreCompanyAccountRequest;
use App\Modules\CompanyAccounts\Http\Requests\StoreCompanyMemberRequest;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CompanyAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = CompanyAccount::query()->where('status', CompanyAccountStatus::Active->value);
        if (! $user->can('company-credit.view')) {
            $query->whereHas('members', fn ($members) => $members->where('user_id', $user->getKey())->where('status', CompanyMemberStatus::Active->value));
        }

        return response()->json(['data' => $query->orderBy('name')->get()->map(fn (CompanyAccount $account): array => $this->serialize($account, $user))]);
    }

    public function store(StoreCompanyAccountRequest $request): JsonResponse
    {
        $account = CompanyAccount::query()->create($request->validated() + ['created_by' => $request->user()->getKey(), 'updated_by' => $request->user()->getKey()]);
        activity()->performedOn($account)->causedBy($request->user())->log('company_account_created');

        return response()->json(['data' => $this->serialize($account, $request->user())], 201);
    }

    public function update(StoreCompanyAccountRequest $request, CompanyAccount $companyAccount): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manage', CompanyAccount::class);
        $companyAccount->update($request->validated() + ['updated_by' => $request->user()->getKey()]);
        activity()->performedOn($companyAccount)->causedBy($request->user())->log('company_account_updated');

        return response()->json(['data' => $this->serialize($companyAccount->fresh(), $request->user())]);
    }

    public function deactivate(Request $request, CompanyAccount $companyAccount): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manage', CompanyAccount::class);
        $companyAccount->update(['status' => CompanyAccountStatus::Inactive, 'updated_by' => $request->user()->getKey()]);
        activity()->performedOn($companyAccount)->causedBy($request->user())->log('company_account_deactivated');

        return response()->json(['data' => $this->serialize($companyAccount->fresh(), $request->user())]);
    }

    public function addMember(StoreCompanyMemberRequest $request, CompanyAccount $companyAccount): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manage', CompanyAccount::class);
        $user = User::query()->where('uuid', $request->string('user_uuid')->toString())->firstOrFail();
        $role = $request->string('role')->toString();

        $member = DB::transaction(function () use ($companyAccount, $user, $role): CompanyAccountMember {
            $lockedCompanyAccount = CompanyAccount::query()
                ->whereKey($companyAccount->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($role === 'referent' && $lockedCompanyAccount->members()->where('role', 'referent')->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['role' => 'Un seul référent actif est autorisé par compte entreprise.']);
            }

            return CompanyAccountMember::query()->updateOrCreate(
                ['company_account_id' => $lockedCompanyAccount->getKey(), 'user_id' => $user->getKey()],
                ['role' => $role, 'status' => CompanyMemberStatus::Active],
            );
        });
        activity()->performedOn($companyAccount)->causedBy($request->user())->withProperties(['member_uuid' => $user->uuid, 'role' => $role])->log('company_account_member_updated');

        return response()->json(['data' => ['uuid' => $member->uuid, 'user_uuid' => $user->uuid, 'role' => $member->role->value, 'status' => $member->status->value]], 201);
    }

    /** @return array<string, mixed> */
    private function serialize(CompanyAccount $account, User $viewer): array
    {
        return array_filter([
            'uuid' => $account->uuid,
            'name' => $account->name,
            'type' => $account->type->value,
            'reference' => $account->reference,
            'phone' => $account->phone,
            'email' => $account->email,
            'status' => $account->status->value,
            'monthly_credit_limit' => $viewer->can('company-credit.view') ? $account->monthly_credit_limit : null,
            'available_credit' => $viewer->can('company-credit.view') ? $account->availableCredit() : null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
