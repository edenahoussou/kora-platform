<?php

use App\Modules\Expenses\Models\Expense;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\Profitability\Data\ProfitabilityAxis;
use App\Modules\Profitability\Services\ProfitabilityReport;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Database\Seeders\KoraProfitabilityDemoSeeder;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 12:00:00', RevenuePeriod::TIMEZONE));
    $this->seed(KoraBusRolePermissionSeeder::class);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('seeds an idempotent profitability demo dataset with revenue and attributed costs', function (): void {
    $this->seed(KoraProfitabilityDemoSeeder::class);
    $this->seed(KoraProfitabilityDemoSeeder::class);

    $report = app(ProfitabilityReport::class)->build(
        RevenuePeriod::fromFilters(['period' => 'this_month']),
        ProfitabilityAxis::Bus,
    );

    expect(Expense::query()->count())->toBe(10)
        ->and($report['hero']['revenueNet'])->toBeGreaterThan(0)
        ->and($report['hero']['costAssigned'])->toBeGreaterThan(0)
        ->and($report['hero']['costUnassigned'])->toBe(14_000)
        ->and($report['tco'])->not->toBeEmpty()
        ->and($report['empty'])->toBeFalse();
});
