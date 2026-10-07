<?php

use App\Models\User;
use App\Modules\CompanyAccounts\Actions\GenerateCompanyStatementPdfAction;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use App\Modules\CompanyAccounts\Models\CompanyBillingStatement;
use App\Modules\CompanyAccounts\Services\CompanyBillingStatementDocumentService;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

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

function detailBillingStatement(CompanyAccount $account): CompanyBillingStatement
{
    return CompanyBillingStatement::query()->create([
        'company_account_id' => $account->id,
        'period_key' => '2026-09',
        'reference' => 'RLV-2026-09-ATL',
        'status' => 'available',
        'currency' => 'XOF',
        'debit_total' => 45000,
        'refund_total' => 8000,
        'net_total' => 37000,
        'reservations_count' => 2,
        'invoices_count' => 2,
        'lines' => [
            [
                'type' => 'debit',
                'reference' => 'DEB-2026-0928-01',
                'amount' => 16200,
                'occurredAt' => '2026-09-28T09:15:00Z',
                'reservation' => ['uuid' => Str::uuid()->toString(), 'reference' => 'RES-2026-08412', 'itinerary' => 'Cotonou → Parakou', 'passengers' => 2],
                'invoice' => ['uuid' => Str::uuid()->toString(), 'koraReference' => 'KOR-INV-8412', 'status' => 'certified', 'totalWithTax' => 16200],
            ],
            [
                'type' => 'refund',
                'reference' => 'REF-2026-0918-01',
                'amount' => 8000,
                'occurredAt' => '2026-09-18T16:00:00Z',
                'reservation' => null,
                'invoice' => null,
            ],
        ],
        'generated_at' => now(),
        'emailed_at' => now(),
    ]);
}

function statementPdfAction(): MockInterface
{
    return Mockery::mock(GenerateCompanyStatementPdfAction::class, [
        app(CompanyBillingStatementDocumentService::class),
    ])->makePartial()->shouldAllowMockingProtectedMethods();
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

it('exports reservations as an Excel workbook when format=xlsx', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('daf');
    $account = detailCompanyAccount();

    $response = $this->actingAs($admin)
        ->get(route('companies.reservations.export', [
            'companyAccount' => $account->uuid,
            'format' => 'xlsx',
        ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
});

it('exports the statement as a branded KORA PDF document', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('daf');
    $account = detailCompanyAccount();
    $statement = detailBillingStatement($account);

    $action = statementPdfAction();
    $action->shouldReceive('renderPdf')
        ->once()
        ->withArgs(function (string $html): bool {
            // Les fonts/logo en data-URI base64 peuvent contenir ces
            // sous-chaînes par hasard — on les exclut avant l'assertion.
            $visible = preg_replace('/data:[^"\')\s]+/', '', $html);
            expect($visible)->toContain('RELEVÉ')
                ->toContain('RLV-2026-09-ATL')
                ->toContain('Septembre 2026')
                ->toContain('Société Atlantique')
                ->toContain('ORG-2026-ATL')
                ->toContain('RES-2026-08412')
                ->toContain('KOR-INV-8412')
                ->toContain('Net à régler');

            return true;
        })
        ->andReturn('%PDF-KORA statement');
    $this->instance(GenerateCompanyStatementPdfAction::class, $action);

    $this->actingAs($admin)
        ->get(route('companies.statements.export', [
            'companyAccount' => $account->uuid,
            'statement' => $statement->uuid,
            'format' => 'pdf',
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="releve-RLV-2026-09-ATL.pdf"');
});

it('exports the statement as an Excel workbook with summary and operations sheets', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('daf');
    $account = detailCompanyAccount();
    $statement = detailBillingStatement($account);

    $response = $this->actingAs($admin)
        ->get(route('companies.statements.export', [
            'companyAccount' => $account->uuid,
            'statement' => $statement->uuid,
            'format' => 'xlsx',
        ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
    expect($response->headers->get('content-disposition'))->toContain('releve-RLV-2026-09-ATL.xlsx');
});

it('returns 404 when exporting a statement of another account or a fake uuid', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('daf');
    $account = detailCompanyAccount();
    $otherAccount = detailCompanyAccount(['name' => 'Autre Org', 'reference' => 'ORG-2026-OTH']);
    $statement = detailBillingStatement($otherAccount);

    $this->actingAs($admin)
        ->get(route('companies.statements.export', [
            'companyAccount' => $account->uuid,
            'statement' => $statement->uuid,
        ]))
        ->assertNotFound();

    $this->actingAs($admin)
        ->get(route('companies.statements.export', [
            'companyAccount' => $account->uuid,
            'statement' => 'stmt-fixture-1',
        ]))
        ->assertNotFound();
});

it('denies statement export to a portal-only referent', function (): void {
    $user = User::factory()->create();
    $user->assignRole('passenger');
    $account = detailCompanyAccount();
    $statement = detailBillingStatement($account);

    CompanyAccountMember::query()->create([
        'company_account_id' => $account->id,
        'user_id' => $user->id,
        'role' => 'referent',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('companies.statements.export', [
            'companyAccount' => $account->uuid,
            'statement' => $statement->uuid,
        ]))
        ->assertForbidden();
});
