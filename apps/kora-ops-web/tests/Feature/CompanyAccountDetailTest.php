<?php

use App\Models\User;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function detailCompanyAccount(array $attributes = []): CompanyAccount
{
    return CompanyAccount::query()->create([
        'name' => 'Société Atlantique',
        'type' => 'company',
        'reference' => 'ORG-2026-ATL',
        'monthly_credit_limit' => 2500000,
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'status' => 'active',
        'city' => 'Cotonou',
        'country' => 'BJ',
        ...$attributes,
    ]);
}

it('renders the company account detail page with 5 tabs and aside for internal staff', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('daf');
    $account = detailCompanyAccount();

    $this->actingAs($admin)
        ->get(route('companies.show', $account->uuid))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies/detail')
            ->where('account.uuid', $account->uuid)
            ->where('account.name', 'Société Atlantique')
            ->where('account.monthlyCreditLimit', 2500000)
            ->where('activeTab', 'credit')
            ->has('credit.holds')
            ->has('credit.transactions')
            ->has('deplacements.reservations')
            ->has('factures.statements')
            ->has('factures.fiscalInvoices')
            ->has('referent.profile')
            ->has('referent.travelers')
            ->has('historique.events')
            ->has('aside.creditSummary')
            ->has('aside.recentEvents')
            ->missing('account.id'),
        );
});

it('accepts a tab query param to switch default tab', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('daf');
    $account = detailCompanyAccount();

    $this->actingAs($admin)
        ->get(route('companies.show', ['companyAccount' => $account->uuid, 'tab' => 'deplacements']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies/detail')
            ->where('activeTab', 'deplacements'),
        );
});

it('denies the company detail page to a portal-only referent', function (): void {
    $user = User::factory()->create();
    $user->assignRole('passenger');
    $account = detailCompanyAccount();

    CompanyAccountMember::query()->create([
        'company_account_id' => $account->id,
        'user_id' => $user->id,
        'role' => 'referent',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('companies.show', $account->uuid))
        ->assertForbidden();
});

it('denies the company detail page to a passenger-only user', function (): void {
    $user = User::factory()->create();
    $user->assignRole('passenger');
    $account = detailCompanyAccount();

    $this->actingAs($user)
        ->get(route('companies.show', $account->uuid))
        ->assertForbidden();
});

it('redirects guests to login on company detail', function (): void {
    $account = detailCompanyAccount();

    $this->get(route('companies.show', $account->uuid))
        ->assertRedirect(route('login'));
});

it('renders the reservation detail page in admin context', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('daf');
    $account = detailCompanyAccount();

    $this->actingAs($admin)
        ->get(route('companies.reservations.show', [
            'companyAccount' => $account->uuid,
            'reservation' => 'res-fixture-1',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies/achat-detail')
            ->where('account.uuid', $account->uuid)
            ->where('reservation.reference', 'RES-2026-08412'),
        );
});

it('renders the statement detail page in admin context', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('daf');
    $account = detailCompanyAccount();

    $this->actingAs($admin)
        ->get(route('companies.statements.show', [
            'companyAccount' => $account->uuid,
            'statement' => 'stmt-fixture-1',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies/releve-detail')
            ->where('account.uuid', $account->uuid)
            ->where('statement.reference', 'RLV-2026-09-001'),
        );
});

it('exports reservations as a streamed CSV file in admin context', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('daf');
    $account = detailCompanyAccount();

    $response = $this->actingAs($admin)
        ->get(route('companies.reservations.export', [
            'companyAccount' => $account->uuid,
        ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});
