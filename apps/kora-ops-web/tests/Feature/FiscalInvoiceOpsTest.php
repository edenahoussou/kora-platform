<?php

use App\Models\User;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Services\FiscalInvoiceQuery;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

it('renders the fiscal invoice queue for DAF with filtered counters and public props', function (): void {
    $daf = User::factory()->create();
    $daf->assignRole('daf');

    $this->actingAs($daf)
        ->get(route('fiscal-invoices.index', ['status' => 'failed']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('fiscal-invoices')
            ->where('filters.status', 'failed')
            ->where('counters.total', 0)
            ->missing('invoices.data.0.id'));
});

it('keeps fiscal retry and encrypted payload permissions separate', function (): void {
    $daf = User::factory()->create();
    $daf->assignRole('daf');
    $generalManagement = User::factory()->create();
    $generalManagement->assignRole('general_management');
    $invoice = new FiscalInvoice(['status' => 'failed']);

    expect(Gate::forUser($daf)->allows('retry', $invoice))->toBeTrue()
        ->and(Gate::forUser($daf)->allows('viewPayload', $invoice))->toBeTrue()
        ->and(Gate::forUser($generalManagement)->allows('view', $invoice))->toBeTrue()
        ->and(Gate::forUser($generalManagement)->allows('retry', $invoice))->toBeFalse()
        ->and(Gate::forUser($generalManagement)->allows('viewPayload', $invoice))->toBeFalse();
});

it('limits credit note initiation to DAF and system administrators', function (): void {
    $daf = User::factory()->create();
    $daf->assignRole('daf');
    $generalManagement = User::factory()->create();
    $generalManagement->assignRole('general_management');
    $agencyManager = User::factory()->create();
    $agencyManager->assignRole('agency_manager');
    $systemAdmin = User::factory()->create();
    $systemAdmin->assignRole('system_admin');
    $invoice = new FiscalInvoice;

    expect(Gate::forUser($daf)->allows('credit', $invoice))->toBeTrue()
        ->and(Gate::forUser($systemAdmin)->allows('credit', $invoice))->toBeTrue()
        ->and(Gate::forUser($generalManagement)->allows('credit', $invoice))->toBeFalse()
        ->and(Gate::forUser($agencyManager)->allows('credit', $invoice))->toBeFalse();
});

it('allows the fiscal queue for DAF, general management and system administrators only', function (): void {
    foreach (['daf', 'general_management', 'system_admin'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('fiscal-invoices.index'))
            ->assertSuccessful();
    }

    $agencyManager = User::factory()->create();
    $agencyManager->assignRole('agency_manager');

    $this->actingAs($agencyManager)
        ->get(route('fiscal-invoices.index'))
        ->assertForbidden();
});

it('builds fiscal counters from an aggregate-only query', function (): void {
    $aggregateSql = null;

    DB::listen(function (QueryExecuted $query) use (&$aggregateSql): void {
        if (str_contains($query->sql, 'count(*) as total')) {
            $aggregateSql = $query->sql;
        }
    });

    $counters = app(FiscalInvoiceQuery::class)->counters([
        'q' => '',
        'status' => 'all',
        'from' => '',
        'to' => '',
    ]);

    expect($counters)->toHaveKeys(['total', 'pending', 'certified', 'failed', 'canceled'])
        ->and($aggregateSql)->not->toBeNull()
        ->and($aggregateSql)->not->toContain('sync_attempts_count')
        ->and($aggregateSql)->not->toContain('credit_notes_count');
});
