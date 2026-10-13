<?php

use App\Modules\Support\Queue\IdempotentFailedJobProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('uses the idempotent failed job provider', function (): void {
    expect(app('queue.failer'))->toBeInstanceOf(IdempotentFailedJobProvider::class);
});

it('logs a failed job twice for the same payload uuid without error', function (): void {
    $uuid = (string) Str::uuid();
    $payload = json_encode(['uuid' => $uuid, 'displayName' => 'App\\Jobs\\ExampleJob']);

    $failer = app('queue.failer');

    expect($failer->log('redis', 'default', $payload, new RuntimeException('first fail')))->toBe($uuid)
        ->and($failer->log('redis', 'default', $payload, new RuntimeException('second fail')))->toBe($uuid);

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->count())->toBe(1);
});
