<?php

use App\Modules\IdentityAccess\Actions\GenerateAgencyCodeAction;

it('generates a code matching the stable AG-###### format', function (): void {
    $code = app(GenerateAgencyCodeAction::class)->execute();

    expect($code)->toMatch('/^AG-\d{6}$/');
});

it('generates sequential codes across successive calls', function (): void {
    $action = app(GenerateAgencyCodeAction::class);

    $first = $action->execute();
    $second = $action->execute();
    $third = $action->execute();

    $firstNumber = (int) substr($first, 3);
    $secondNumber = (int) substr($second, 3);
    $thirdNumber = (int) substr($third, 3);

    expect($secondNumber)->toBe($firstNumber + 1)
        ->and($thirdNumber)->toBe($secondNumber + 1);
});

it('never repeats a code across many successive calls, simulating high volume creation', function (): void {
    $action = app(GenerateAgencyCodeAction::class);

    $codes = collect(range(1, 100))
        ->map(fn () => $action->execute());

    expect($codes->unique())->toHaveCount(100);
});

it('persists the sequence position across separate transactions', function (): void {
    $action = app(GenerateAgencyCodeAction::class);

    $before = (int) substr($action->execute(), 3);

    // A later, independent call (e.g. a subsequent HTTP request) must
    // continue from where the sequence left off, not restart.
    $after = (int) substr($action->execute(), 3);

    expect($after)->toBe($before + 1);
});
