<?php

use App\Models\User;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use Database\Seeders\KoraBusRolePermissionSeeder;
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
