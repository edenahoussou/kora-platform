<?php

declare(strict_types=1);

it('keeps sentry disabled without a dsn so the app runs normally', function (): void {
    config(['sentry.dsn' => null]);

    $this->get('/up')->assertOk();
});

it('never sends default pii to sentry', function (): void {
    expect(config('sentry.send_default_pii'))->toBeFalse();
});
