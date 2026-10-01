<?php

use App\Modules\Fiscalization\Support\EmecfTaxVentilation;

it('subtracts the specific tax from the taxable base and exposes it on the carrying group', function (): void {
    $taxes = [
        'tab' => 31180,
        'hab' => 26424,
        'vab' => 4756,
        'ts' => 1000,
        'total' => 31180,
    ];

    $items = [
        ['taxGroup' => 'B', 'quantity' => 3, 'taxSpecific' => 1000],
    ];

    $rows = EmecfTaxVentilation::fromTaxes($taxes, items: $items);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['label'])->toBe('B - TVA 18%')
        ->and($rows[0]['total'])->toBe(31180.0)
        ->and($rows[0]['imposable'])->toBe(25424.0)
        ->and($rows[0]['ts'])->toBe(1000.0)
        ->and($rows[0]['impot'])->toBe(4756.0);
});

it('keeps the taxable base untouched when there is no specific tax', function (): void {
    $rows = EmecfTaxVentilation::fromTaxes([
        'taa' => 20000,
        'tab' => 30000,
        'hab' => 25424,
        'vab' => 4576,
        'total' => 50000,
    ]);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['label'])->toBe('A - EXONERE')
        ->and($rows[0]['imposable'])->toBeNull()
        ->and($rows[1]['imposable'])->toBe(25424.0)
        ->and($rows[1]['ts'])->toBe(0.0);
});

it('negates the specific tax rows for credit notes', function (): void {
    $rows = EmecfTaxVentilation::fromTaxes(
        ['tab' => -31180, 'hab' => -26424, 'vab' => -4756, 'ts' => -1000, 'total' => -31180],
        items: [['taxGroup' => 'B', 'quantity' => -3, 'taxSpecific' => 1000]],
    );

    expect($rows[0]['imposable'])->toBe(-25424.0)
        ->and($rows[0]['ts'])->toBe(-1000.0);
});

it('decomposes an item specific tax like the DGI verification invoice', function (): void {
    $specificTax = EmecfTaxVentilation::itemSpecificTax(
        ['taxGroup' => 'B', 'quantity' => 3, 'taxSpecific' => 1000],
        ['tb' => 18],
    );

    expect($specificTax['unit'])->toBe(1000 / 3)
        ->and($specificTax['factor'])->toBe(1.18)
        ->and($specificTax['amount'])->toBe(1180.0);
});
