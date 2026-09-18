<?php

use App\Models\User;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use App\Modules\CompanyAccounts\Models\CompanyCreditTransaction;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function companyAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('daf');

    return $user;
}

it('renders the company account list with public identifiers and credit metrics', function (): void {
    $user = companyAdmin();
    $account = CompanyAccount::query()->create([
        'name' => 'ONG Horizon',
        'type' => 'ngo',
        'reference' => 'HZN-001',
        'monthly_credit_limit' => 500000,
        'status' => 'active',
        'city' => 'Cotonou',
        'country' => 'BJ',
    ]);

    CompanyCreditTransaction::query()->create([
        'company_account_id' => $account->id,
        'type' => 'debit',
        'amount' => 125000,
        'period_key' => now()->format('Y-m'),
        'reference' => 'DEBIT-TEST-001',
    ]);

    $this->actingAs($user)
        ->get(route('companies.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies')
            ->where('accounts.data.0.uuid', $account->uuid)
            ->missing('accounts.data.0.id')
            ->where('accounts.data.0.availableCredit', 375000)
            ->where('accounts.data.0.city', 'Cotonou')
            ->where('summary.total', 1)
            ->where('summary.active', 1)
            ->where('permissions.canViewCredit', true),
        );
});

it('generates a reference and stores legal fields when creating an organisation', function (): void {
    $user = companyAdmin();

    $this->actingAs($user)
        ->post(route('companies.store'), [
            'name' => 'Société Atlantique',
            'type' => 'company',
            'phone' => '+22997000000',
            'email' => 'contact@atlantique.test',
            'address' => 'Boulevard de la Marina',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'ifu' => '1234567890123',
            'rccm' => 'RB/COT/123',
            'monthly_credit_limit' => 750000,
        ])
        ->assertRedirect(route('companies.index', absolute: false));

    $account = CompanyAccount::query()->where('name', 'Société Atlantique')->firstOrFail();

    expect($account->reference)->toMatch('/^ORG-\d{4}-[A-Z0-9]{6}$/')
        ->and($account->country)->toBe('BJ')
        ->and($account->ifu)->toBe('1234567890123')
        ->and($account->monthly_credit_limit)->toBe(750000);
});

it('requires a phone when creating an organisation', function (): void {
    $user = companyAdmin();
    $payload = [
        'name' => 'Société Validation',
        'type' => 'company',
        'monthly_credit_limit' => 100000,
    ];

    $this->actingAs($user)
        ->post(route('companies.store'), $payload)
        ->assertSessionHasErrors('phone');

    expect(CompanyAccount::query()->where('name', 'Société Validation')->exists())->toBeFalse();
});

it('rejects an update that lowers the monthly credit limit below confirmed debits', function (): void {
    $user = companyAdmin();
    $account = CompanyAccount::query()->create([
        'name' => 'ONG Horizon',
        'type' => 'ngo',
        'reference' => 'HZN-001',
        'monthly_credit_limit' => 500000,
        'status' => 'active',
    ]);
    CompanyCreditTransaction::query()->create([
        'company_account_id' => $account->id,
        'type' => 'debit',
        'amount' => 200000,
        'period_key' => now()->format('Y-m'),
        'reference' => 'DEBIT-TEST-002',
    ]);

    $this->actingAs($user)
        ->put(route('companies.update', $account), [
            'name' => $account->name,
            'type' => $account->type->value,
            'phone' => '+22997000000',
            'monthly_credit_limit' => 100000,
        ])
        ->assertSessionHasErrors('monthly_credit_limit');

    expect($account->refresh()->monthly_credit_limit)->toBe(500000);
});

it('allows a manager to change status and manage a single referent', function (): void {
    $user = companyAdmin();
    $account = CompanyAccount::query()->create([
        'name' => 'Ambassade du Bénin',
        'type' => 'embassy',
        'reference' => 'AMB-001',
        'monthly_credit_limit' => 0,
        'status' => 'active',
    ]);
    $referent = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('companies.status', $account), ['status' => 'inactive'])
        ->assertRedirect(route('companies.index', absolute: false));

    $this->actingAs($user)
        ->post(route('companies.members.store', $account), [
            'user_uuid' => $referent->uuid,
            'role' => 'referent',
        ])
        ->assertRedirect(route('companies.index', absolute: false));

    $replacement = User::factory()->create();

    $this->actingAs($user)
        ->post(route('companies.members.store', $account), [
            'user_uuid' => $replacement->uuid,
            'role' => 'referent',
        ])
        ->assertRedirect(route('companies.index', absolute: false));

    expect($account->refresh()->status->value)->toBe('inactive')
        ->and(CompanyAccountMember::query()->where('company_account_id', $account->id)->where('role', 'referent')->where('status', 'active')->count())->toBe(1)
        ->and(CompanyAccountMember::query()->where('company_account_id', $account->id)->where('user_id', $referent->id)->firstOrFail()->status->value)->toBe('inactive');
});

it('only offers non-internal users as member candidates', function (): void {
    $user = companyAdmin();
    $account = CompanyAccount::query()->create([
        'name' => 'Société Voyageurs',
        'type' => 'company',
        'reference' => 'STV-001',
        'monthly_credit_limit' => 0,
        'status' => 'active',
    ]);

    $passenger = User::factory()->create();
    $passenger->assignRole('passenger');
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $this->actingAs($user)
        ->get(route('companies.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies')
            ->where('userCandidates', fn ($candidates): bool => collect($candidates)->pluck('uuid')->contains($passenger->uuid)
                && ! collect($candidates)->pluck('uuid')->contains($agent->uuid)
                && ! collect($candidates)->pluck('uuid')->contains($user->uuid)),
        );

    $this->actingAs($user)
        ->post(route('companies.members.store', $account), [
            'user_uuid' => $agent->uuid,
            'role' => 'member',
        ])
        ->assertSessionHasErrors('user_uuid');
});

it('forbids users without company account view permission', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('companies.index'))
        ->assertForbidden();
});
