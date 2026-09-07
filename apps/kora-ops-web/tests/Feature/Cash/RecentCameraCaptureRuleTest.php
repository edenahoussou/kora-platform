<?php

use App\Modules\Cash\Rules\RecentCameraCapture;
use Illuminate\Support\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

function assertRuleFails(RecentCameraCapture $rule, mixed $value): ?string
{
    $message = null;
    $rule->validate('evidence_captured_at', $value, function (string $text) use (&$message): void {
        $message = $text;
    });

    return $message;
}

it('passes for a timestamp captured just now', function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');

    $rule = new RecentCameraCapture;

    expect(assertRuleFails($rule, now()->toISOString()))->toBeNull();
});

it('passes for a timestamp within the freshness tolerance', function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');

    $rule = new RecentCameraCapture(toleranceMinutes: 15);

    expect(assertRuleFails($rule, now()->subMinutes(10)->toISOString()))->toBeNull();
});

it('fails for a timestamp older than the freshness tolerance', function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');

    $rule = new RecentCameraCapture(toleranceMinutes: 15);

    expect(assertRuleFails($rule, now()->subMinutes(16)->toISOString()))->not->toBeNull();
});

it('fails for a timestamp further in the future than the clock skew tolerance', function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');

    $rule = new RecentCameraCapture(clockSkewMinutes: 2);

    expect(assertRuleFails($rule, now()->addMinutes(3)->toISOString()))->not->toBeNull();
});

it('passes for a timestamp within the clock skew tolerance', function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');

    $rule = new RecentCameraCapture(clockSkewMinutes: 2);

    expect(assertRuleFails($rule, now()->addMinutes(1)->toISOString()))->toBeNull();
});

it('fails for a missing or empty value', function (): void {
    $rule = new RecentCameraCapture;

    expect(assertRuleFails($rule, null))->not->toBeNull()
        ->and(assertRuleFails($rule, ''))->not->toBeNull();
});

it('fails for a value that cannot be parsed as a date', function (): void {
    $rule = new RecentCameraCapture;

    expect(assertRuleFails($rule, 'not-a-date'))->not->toBeNull();
});
