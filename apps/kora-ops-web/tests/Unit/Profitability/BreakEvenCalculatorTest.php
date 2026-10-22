<?php

use App\Modules\Profitability\Services\BreakEvenCalculator;

it('computes the break-even seats from fixed costs and seat contribution', function (): void {
    $result = (new BreakEvenCalculator)->calculate([
        'revenueNet' => 800_000,
        'costFixed' => 100_000,
        'costVariable' => 300_000,
        'seatsSold' => 40,
        'capacity' => 50,
    ]);

    expect($result['contributionPerSeat'])->toBe(12_500.0)
        ->and($result['breakEvenSeats'])->toBe(8)
        ->and($result['fillRate'])->toBe(80.0)
        ->and($result['status'])->toBe(BreakEvenCalculator::STATUS_ATTEINT);
});

it('flags a departure under its break-even threshold', function (): void {
    $result = (new BreakEvenCalculator)->calculate([
        'revenueNet' => 100_000,
        'costFixed' => 90_000,
        'costVariable' => 60_000,
        'seatsSold' => 10,
        'capacity' => 50,
    ]);

    expect($result['contributionPerSeat'])->toBe(4_000.0)
        ->and($result['breakEvenSeats'])->toBe(23)
        ->and($result['status'])->toBe(BreakEvenCalculator::STATUS_SOUS_SEUIL);
});

it('cannot compute a threshold without sold seats or positive contribution', function (): void {
    $withoutSeats = (new BreakEvenCalculator)->calculate([
        'revenueNet' => 0,
        'costFixed' => 10_000,
        'costVariable' => 0,
        'seatsSold' => 0,
        'capacity' => 50,
    ]);
    $negativeContribution = (new BreakEvenCalculator)->calculate([
        'revenueNet' => 50_000,
        'costFixed' => 10_000,
        'costVariable' => 70_000,
        'seatsSold' => 10,
        'capacity' => 50,
    ]);

    expect($withoutSeats['status'])->toBe(BreakEvenCalculator::STATUS_NON_CALCULABLE)
        ->and($withoutSeats['breakEvenSeats'])->toBeNull()
        ->and($negativeContribution['status'])->toBe(BreakEvenCalculator::STATUS_NON_CALCULABLE);
});
