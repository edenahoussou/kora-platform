<?php

use App\Models\User;
use App\Modules\CompanyAccounts\Actions\ImportCompanyTravelersAction;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use App\Modules\CompanyAccounts\Models\CompanyTraveler;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function rosterAccount(array $attributes = []): CompanyAccount
{
    return CompanyAccount::query()->create([
        'name' => 'Société Meridian',
        'type' => 'company',
        'reference' => 'ORG-'.Str::upper(Str::random(8)),
        'monthly_credit_limit' => 500000,
        'status' => 'active',
        ...$attributes,
    ]);
}

function rosterReferent(string $role = 'referent'): array
{
    $user = User::factory()->create();
    $account = rosterAccount();

    CompanyAccountMember::query()->create([
        'company_account_id' => $account->id,
        'user_id' => $user->id,
        'role' => $role,
        'status' => 'active',
    ]);

    return [$user, $account];
}

function rosterTraveler(CompanyAccount $account, array $attributes = []): CompanyTraveler
{
    return CompanyTraveler::query()->create([
        'company_account_id' => $account->id,
        'first_name' => 'Aïcha',
        'last_name' => 'Mensah',
        'phone' => '+22997000050',
        'status' => 'active',
        ...$attributes,
    ]);
}

/**
 * @param  list<list<mixed>>  $rows
 */
function rosterImportFile(array $rows, string $extension = 'xlsx'): UploadedFile
{
    $headers = ImportCompanyTravelersAction::TEMPLATE_HEADERS;

    if ($extension === 'csv') {
        $path = tempnam(sys_get_temp_dir(), 'roster-import-').'.csv';
        $handle = fopen($path, 'wb');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return new UploadedFile($path, 'voyageurs.csv', 'text/csv', null, true);
    }

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([$headers, ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'roster-import-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile(
        $path,
        'voyageurs.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
}

it('redirects guests to login', function (): void {
    $this->get(route('company-portal.travelers.index'))->assertRedirect(route('login'));
});

it('denies the roster to a user without membership', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('company-portal.travelers.index'))
        ->assertForbidden();
});

it('denies the roster to a non-referent member', function (): void {
    [$user] = rosterReferent('member');

    $this->actingAs($user)
        ->get(route('company-portal.travelers.index'))
        ->assertForbidden();
});

it('denies the roster when the company account is inactive', function (): void {
    [$user, $account] = rosterReferent();
    $account->update(['status' => 'inactive']);

    $this->actingAs($user)
        ->get(route('company-portal.travelers.index'))
        ->assertForbidden();
});

it('renders only the roster of the referent account without numeric ids', function (): void {
    [$user, $account] = rosterReferent();
    $traveler = rosterTraveler($account, [
        'document_type' => 'national_id',
        'document_number' => 'ID-7788',
        'yellow_fever_vaccinated' => true,
    ]);
    [, $otherAccount] = rosterReferent();
    rosterTraveler($otherAccount, ['first_name' => 'Étranger', 'phone' => '+22997000099']);

    $this->actingAs($user)
        ->get(route('company-portal.travelers.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('company-portal/travelers')
            ->where('account.uuid', $account->uuid)
            ->has('travelers.data', 1)
            ->where('travelers.data.0.uuid', $traveler->uuid)
            ->where('travelers.data.0.firstName', 'Aïcha')
            ->where('travelers.data.0.documentTypeLabel', 'Carte nationale')
            ->where('travelers.data.0.status', 'active')
            ->missing('travelers.data.0.id')
            ->missing('travelers.data.0.company_account_id')
            ->has('documentTypes'),
        );
});

it('creates a traveler on the referent own account with normalized phone', function (): void {
    [$user, $account] = rosterReferent();

    $this->actingAs($user)
        ->post(route('company-portal.travelers.store'), [
            'first_name' => 'Koffi',
            'last_name' => 'Agbessi',
            'phone' => '97 00 00 42',
            'document_type' => 'passport',
            'document_number' => 'P-12345',
        ])
        ->assertRedirect(route('company-portal.travelers.index'));

    $traveler = CompanyTraveler::query()->sole();

    expect($traveler->company_account_id)->toBe($account->id)
        ->and($traveler->phone)->toBe('+22997000042')
        ->and($traveler->status->value)->toBe('active')
        ->and($traveler->document_type->value)->toBe('passport');
});

it('validates required fields and the document pair', function (): void {
    [$user] = rosterReferent();

    $this->actingAs($user)
        ->post(route('company-portal.travelers.store'), [])
        ->assertSessionHasErrors(['first_name', 'last_name', 'phone']);

    $this->actingAs($user)
        ->post(route('company-portal.travelers.store'), [
            'first_name' => 'Koffi',
            'last_name' => 'Agbessi',
            'phone' => '97000042',
            'document_number' => 'P-12345',
        ])
        ->assertSessionHasErrors('document_type');
});

it('rejects a duplicate normalized phone within the same account', function (): void {
    [$user, $account] = rosterReferent();
    rosterTraveler($account, ['phone' => '+22997000042']);

    $this->actingAs($user)
        ->post(route('company-portal.travelers.store'), [
            'first_name' => 'Koffi',
            'last_name' => 'Agbessi',
            'phone' => '97000042',
        ])
        ->assertSessionHasErrors('phone');
});

it('allows the same phone in a different account', function (): void {
    [$user, $account] = rosterReferent();
    [, $otherAccount] = rosterReferent();
    rosterTraveler($otherAccount, ['phone' => '+22997000042']);

    $this->actingAs($user)
        ->post(route('company-portal.travelers.store'), [
            'first_name' => 'Koffi',
            'last_name' => 'Agbessi',
            'phone' => '97000042',
        ])
        ->assertRedirect(route('company-portal.travelers.index'));

    expect(
        CompanyTraveler::query()
            ->where('company_account_id', $account->id)
            ->where('phone', '+22997000042')
            ->exists(),
    )->toBeTrue();
});

it('updates a traveler of the referent own account', function (): void {
    [$user, $account] = rosterReferent();
    $traveler = rosterTraveler($account);

    $this->actingAs($user)
        ->put(route('company-portal.travelers.update', $traveler), [
            'first_name' => 'Aïcha',
            'last_name' => 'Mensah',
            'phone' => '+22997000050',
            'email' => 'aicha@meridian.bj',
        ])
        ->assertRedirect(route('company-portal.travelers.index'));

    expect($traveler->refresh()->email)->toBe('aicha@meridian.bj');
});

it('deactivates and reactivates a traveler', function (): void {
    [$user, $account] = rosterReferent();
    $traveler = rosterTraveler($account);

    $this->actingAs($user)
        ->patch(route('company-portal.travelers.status', $traveler), ['status' => 'inactive'])
        ->assertRedirect();

    expect($traveler->refresh()->status->value)->toBe('inactive');

    $this->actingAs($user)
        ->patch(route('company-portal.travelers.status', $traveler), ['status' => 'active'])
        ->assertRedirect();

    expect($traveler->refresh()->status->value)->toBe('active');
});

it('returns 404 when updating a traveler of another account', function (): void {
    [$user] = rosterReferent();
    [, $otherAccount] = rosterReferent();
    $foreign = rosterTraveler($otherAccount);

    $this->actingAs($user)
        ->put(route('company-portal.travelers.update', $foreign), [
            'first_name' => 'Aïcha',
            'last_name' => 'Mensah',
            'phone' => '+22997000050',
        ])
        ->assertNotFound();
});

it('returns 404 when changing the status of a traveler of another account', function (): void {
    [$user] = rosterReferent();
    [, $otherAccount] = rosterReferent();
    $foreign = rosterTraveler($otherAccount);

    $this->actingAs($user)
        ->patch(route('company-portal.travelers.status', $foreign), ['status' => 'inactive'])
        ->assertNotFound();

    expect($foreign->refresh()->status->value)->toBe('active');
});

it('filters the roster by status and search query', function (): void {
    [$user, $account] = rosterReferent();
    rosterTraveler($account, ['phone' => '+22997000050']);
    rosterTraveler($account, [
        'first_name' => 'Brice',
        'phone' => '+22997000051',
        'status' => 'inactive',
    ]);

    $this->actingAs($user)
        ->get(route('company-portal.travelers.index', ['status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('travelers.data', 1)
            ->where('travelers.data.0.firstName', 'Brice')
            ->where('filters.status', 'inactive'),
        );

    $this->actingAs($user)
        ->get(route('company-portal.travelers.index', ['q' => '97000051']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('travelers.data', 1)
            ->where('travelers.data.0.firstName', 'Brice'),
        );
});

it('exposes only active travelers through the eligible endpoint', function (): void {
    [$user, $account] = rosterReferent();
    rosterTraveler($account, ['phone' => '+22997000050']);
    rosterTraveler($account, ['phone' => '+22997000051', 'status' => 'inactive']);
    [, $otherAccount] = rosterReferent();
    rosterTraveler($otherAccount, ['phone' => '+22997000052']);

    $response = $this->actingAs($user)
        ->getJson(route('company-portal.travelers.eligible'))
        ->assertSuccessful();

    $phones = collect($response->json('travelers'))->pluck('phone');

    expect($phones)->toContain('+22997000050')
        ->not->toContain('+22997000051')
        ->not->toContain('+22997000052');

    expect($response->json('travelers.0.id'))->toBeNull();
});

it('requires authentication and referent role to import or download the template', function (): void {
    $this->post(route('company-portal.travelers.import'))
        ->assertRedirect(route('login'));
    $this->get(route('company-portal.travelers.template'))
        ->assertRedirect(route('login'));

    [$member] = rosterReferent('member');

    $this->actingAs($member)
        ->post(route('company-portal.travelers.import'))
        ->assertForbidden();
    $this->actingAs($member)
        ->get(route('company-portal.travelers.template'))
        ->assertForbidden();
});

it('lets the referent download a readable xlsx template', function (): void {
    [$user] = rosterReferent();

    $response = $this->actingAs($user)
        ->get(route('company-portal.travelers.template'))
        ->assertSuccessful();

    $response->assertHeader(
        'content-type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );
    expect($response->headers->get('content-disposition'))
        ->toContain('modele-membres-voyageurs.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'template-check-').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $reloaded = IOFactory::load($path);
    $headers = $reloaded->getActiveSheet()->toArray(null, true, true, false)[0];
    $reloaded->disconnectWorksheets();

    expect($headers)->toBe(ImportCompanyTravelersAction::TEMPLATE_HEADERS);
});

it('imports valid xlsx rows onto the referent account with normalized phones', function (): void {
    [$user, $account] = rosterReferent();
    [, $otherAccount] = rosterReferent();

    $file = rosterImportFile([
        ['Koffi', 'Agbessi', '97 00 00 42', 'koffi@meridian.bj', 'Passeport', 'P-12345', 'TG', '1990-05-12', 'oui'],
        ['Aïcha', 'Mensah', '+22997000043', '', '', '', 'BJ', '', 'non'],
    ]);

    $this->actingAs($user)
        ->post(route('company-portal.travelers.import'), ['file' => $file])
        ->assertRedirect(route('company-portal.travelers.index'))
        ->assertSessionHas('inertia.flash_data.importReport.imported', 2);

    $travelers = $account->travelers()->orderBy('phone')->get();

    expect($travelers)->toHaveCount(2)
        ->and($travelers[0]->phone)->toBe('+22997000042')
        ->and($travelers[0]->document_type->value)->toBe('passport')
        ->and($travelers[0]->yellow_fever_vaccinated)->toBeTrue()
        ->and($travelers[1]->phone)->toBe('+22997000043')
        ->and($otherAccount->travelers()->count())->toBe(0);
});

it('updates an existing traveler when the phone already exists', function (): void {
    [$user, $account] = rosterReferent();
    $existing = rosterTraveler($account, ['phone' => '+22997000042']);

    $file = rosterImportFile([
        ['Koffi', 'Agbessi', '97000042', 'new@meridian.bj', '', '', 'BJ', '', ''],
        ['Nouveau', 'Voyageur', '97000044', '', '', '', 'BJ', '', ''],
    ]);

    $this->actingAs($user)
        ->post(route('company-portal.travelers.import'), ['file' => $file])
        ->assertRedirect(route('company-portal.travelers.index'))
        ->assertSessionHas('inertia.flash_data.importReport.imported', 1)
        ->assertSessionHas('inertia.flash_data.importReport.updated', 1);

    expect($account->travelers()->count())->toBe(2)
        ->and($existing->refresh()->first_name)->toBe('Koffi')
        ->and($existing->email)->toBe('new@meridian.bj');
});

it('reports invalid rows without aborting the import', function (): void {
    [$user, $account] = rosterReferent();

    $file = rosterImportFile([
        ['Koffi', 'Agbessi', '97000042', '', '', '', 'BJ', '', ''],
        ['', 'SansPrenom', '97000045', '', '', '', 'BJ', '', ''],
        ['Mauvais', 'Email', '97000046', 'pas-un-email', '', '', 'BJ', '', ''],
    ]);

    $this->actingAs($user)
        ->post(route('company-portal.travelers.import'), ['file' => $file])
        ->assertRedirect(route('company-portal.travelers.index'))
        ->assertSessionHas('inertia.flash_data.importReport.imported', 1)
        ->assertSessionHas('inertia.flash_data.importReport.errors.0.row', 3);

    expect($account->travelers()->count())->toBe(1)
        ->and($account->travelers()->sole()->phone)->toBe('+22997000042');
});

it('rejects unsupported or oversized import files', function (): void {
    [$user] = rosterReferent();

    $this->actingAs($user)
        ->post(route('company-portal.travelers.import'), [
            'file' => UploadedFile::fake()->create('voyageurs.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');

    $this->actingAs($user)
        ->post(route('company-portal.travelers.import'), [])
        ->assertSessionHasErrors('file');
});
