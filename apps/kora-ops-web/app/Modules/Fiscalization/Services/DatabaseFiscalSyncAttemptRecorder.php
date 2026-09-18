<?php

namespace App\Modules\Fiscalization\Services;

use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Models\FiscalSyncAttempt;
use Illuminate\Support\Arr;

class DatabaseFiscalSyncAttemptRecorder implements FiscalSyncAttemptRecorder
{
    /** @param array<string, mixed> $payload */
    public function start(FiscalInvoice $invoice, string $operation, int $attemptNumber, array $payload): FiscalSyncAttempt
    {
        return $invoice->syncAttempts()->create([
            'operation' => $operation,
            'attempt_number' => $attemptNumber,
            'request_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'status' => 'pending',
            'attempted_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    public function succeed(FiscalSyncAttempt $attempt, array $payload, ?string $responseCode = null): void
    {
        $attempt->update([
            'status' => 'success',
            'response_code' => $responseCode,
            'response_payload' => $this->summary($payload),
            'response_payload_encrypted' => $this->redact($payload),
            'completed_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    public function fail(FiscalSyncAttempt $attempt, \Throwable $exception, array $payload = []): void
    {
        $attempt->update([
            'status' => 'failed',
            'error_code' => class_basename($exception),
            'error_message' => $exception->getMessage(),
            'response_payload' => $this->summary($payload),
            'response_payload_encrypted' => $this->redact($payload),
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function summary(array $payload): array
    {
        return Arr::only($payload, ['success', 'uid', 'invoice_id', 'code', 'message', 'error', 'status']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        $sensitive = ['token', 'secret', 'password', 'authorization', 'api_key', 'private_key'];

        return collect($payload)->mapWithKeys(function (mixed $value, string|int $key) use ($sensitive): array {
            $keyString = mb_strtolower((string) $key);
            if (collect($sensitive)->contains(fn (string $needle): bool => str_contains($keyString, $needle))) {
                return [$key => '[redacted]'];
            }

            return [$key => is_array($value) ? $this->redact($value) : $value];
        })->all();
    }
}
