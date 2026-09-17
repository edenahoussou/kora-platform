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

/**
 * @group Company Accounts
 *
 * Company account management for group purchases and credit-based billing.
 */
class CompanyAccountController extends Controller
{
    /**
     * List active company accounts visible to the authenticated user.
     *
     * Users with the `company-credit.view` permission see all active accounts.
     * Others see only accounts where they are an active member.
     *
     * @authenticated
     *
     * @response 200 scenario="Company account list" {
     *   "data": [
     *     {
     *       "uuid": "c1234567-89ab-cdef-0123-456789abcdef",
     *       "name": "Société de Transport Bénin SARL",
     *       "type": "company",
     *       "reference": "STB-2026-001",
     *       "phone": "+22901000001",
     *       "email": "contact@stb.bj",
     *       "status": "active",
     *       "monthly_credit_limit": 5000000,
     *       "available_credit": 3250000
     *     }
     *   ]
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = CompanyAccount::query()->where('status', CompanyAccountStatus::Active->value);
        if (! $user->can('company-credit.view')) {
            $query->whereHas('members', fn ($members) => $members->where('user_id', $user->getKey())->where('status', CompanyMemberStatus::Active->value));
        }

        return response()->json(['data' => $query->orderBy('name')->get()->map(fn (CompanyAccount $account): array => $this->serialize($account, $user))]);
    }

    /**
     * Create a new company account.
     *
     * @authenticated
     *
     * @response 201 scenario="Company account created" {
     *   "data": {
     *     "uuid": "c1234567-89ab-cdef-0123-456789abcdef",
     *     "name": "Société de Transport Bénin SARL",
     *     "type": "company",
     *     "reference": "STB-2026-001",
     *     "phone": "+22901000001",
     *     "email": "contact@stb.bj",
     *     "status": "active",
     *     "monthly_credit_limit": 5000000,
     *     "available_credit": 5000000
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "This action is unauthorized."
     * }
     * @response 422 scenario="Validation error" {
     *   "message": "The given data was invalid.",
     *   "errors": {"name": ["The name has already been taken."]}
     * }
     */
    public function store(StoreCompanyAccountRequest $request): JsonResponse
    {
        $account = CompanyAccount::query()->create($request->validated() + ['created_by' => $request->user()->getKey(), 'updated_by' => $request->user()->getKey()]);
        activity()->performedOn($account)->causedBy($request->user())->log('company_account_created');

        return response()->json(['data' => $this->serialize($account, $request->user())], 201);
    }

    /**
     * Update a company account's details.
     *
     * @authenticated
     *
     * @response 200 scenario="Company account updated" {
     *   "data": {
     *     "uuid": "c1234567-89ab-cdef-0123-456789abcdef",
     *     "name": "Société de Transport Bénin SARL",
     *     "type": "company",
     *     "reference": "STB-2026-001",
     *     "phone": "+22901000002",
     *     "email": "contact@stb.bj",
     *     "status": "active",
     *     "monthly_credit_limit": 5000000,
     *     "available_credit": 3250000
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "This action is unauthorized."
     * }
     * @response 422 scenario="Validation error" {
     *   "message": "The given data was invalid.",
     *   "errors": {"email": ["The email has already been taken."]}
     * }
     */
    public function update(StoreCompanyAccountRequest $request, CompanyAccount $companyAccount): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manage', CompanyAccount::class);
        $companyAccount->update($request->validated() + ['updated_by' => $request->user()->getKey()]);
        activity()->performedOn($companyAccount)->causedBy($request->user())->log('company_account_updated');

        return response()->json(['data' => $this->serialize($companyAccount->fresh(), $request->user())]);
    }

    /**
     * Deactivate a company account.
     *
     * Sets the account status to `inactive`. Existing credit balances remain but no new
     * purchases are allowed.
     *
     * @authenticated
     *
     * @response 200 scenario="Company account deactivated" {
     *   "data": {
     *     "uuid": "c1234567-89ab-cdef-0123-456789abcdef",
     *     "name": "Société de Transport Bénin SARL",
     *     "type": "company",
     *     "reference": "STB-2026-001",
     *     "phone": "+22901000001",
     *     "email": "contact@stb.bj",
     *     "status": "inactive",
     *     "monthly_credit_limit": 5000000,
     *     "available_credit": 3250000
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "This action is unauthorized."
     * }
     */
    public function deactivate(Request $request, CompanyAccount $companyAccount): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manage', CompanyAccount::class);
        $companyAccount->update(['status' => CompanyAccountStatus::Inactive, 'updated_by' => $request->user()->getKey()]);
        activity()->performedOn($companyAccount)->causedBy($request->user())->log('company_account_deactivated');

        return response()->json(['data' => $this->serialize($companyAccount->fresh(), $request->user())]);
    }

    /**
     * Add or update a member on a company account.
     *
     * Only one active `referent` role is allowed per account. If the user is already
     * a member, their role and status are updated.
     *
     * @authenticated
     *
     * @response 201 scenario="Member added" {
     *   "data": {
     *     "uuid": "m1234567-89ab-cdef-0123-456789abcdef",
     *     "user_uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
     *     "role": "buyer",
     *     "status": "active"
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "This action is unauthorized."
     * }
     * @response 422 scenario="Duplicate referent" {
     *   "message": "The given data was invalid.",
     *   "errors": {"role": ["Un seul référent actif est autorisé par compte entreprise."]}
     * }
     * @response 404 scenario="User not found" {
     *   "message": "No query results for model [App\\Models\\User]."
     * }
     */
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
            'address' => $account->address,
            'city' => $account->city,
            'country' => $account->country,
            'ifu' => $account->ifu,
            'rccm' => $account->rccm,
            'status' => $account->status->value,
            'monthly_credit_limit' => $viewer->can('company-credit.view') ? $account->monthly_credit_limit : null,
            'available_credit' => $viewer->can('company-credit.view') ? $account->availableCredit() : null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
