<?php

use App\Models\User;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\IdentityAccess\Models\Agency;
use Database\Seeders\KoraBusRolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

it('exports filtered expenses as CSV without formula injection', function (): void {
    $agency = Agency::factory()->create();
    $manager = User::factory()->create(['agency_id' => $agency->id]);
    $manager->assignRole('agency_manager');
    $category = ExpenseCategory::factory()->create();
    Expense::factory()->for($agency)->for($category, 'category')->create([
        'created_by' => $manager->id,
        'payee_label' => '=1+1',
    ]);

    $csv = $this->actingAs($manager)->get(route('expenses.export', ['format' => 'csv']))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain("'=1+1")
        ->and($csv)->toContain('Référence')
        ->and($csv)->not->toContain('e-MECeF');
});

it('exports the fiscal invoice queue as XLSX without exposing payload columns', function (): void {
    $daf = User::factory()->create();
    $daf->assignRole('daf');

    $response = $this->actingAs($daf)->get(route('fiscal-invoices.export', ['format' => 'xlsx']))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect($response->streamedContent())->toStartWith('PK');
});
