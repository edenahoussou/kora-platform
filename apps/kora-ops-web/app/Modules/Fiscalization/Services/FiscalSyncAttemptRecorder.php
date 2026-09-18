<?php

namespace App\Modules\Fiscalization\Services;

use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Models\FiscalSyncAttempt;

interface FiscalSyncAttemptRecorder
{
    /** @param array<string, mixed> $payload */
    public function start(FiscalInvoice $invoice, string $operation, int $attemptNumber, array $payload): FiscalSyncAttempt;

    /** @param array<string, mixed> $payload */
    public function succeed(FiscalSyncAttempt $attempt, array $payload, ?string $responseCode = null): void;

    /** @param array<string, mixed> $payload */
    public function fail(FiscalSyncAttempt $attempt, \Throwable $exception, array $payload = []): void;
}
