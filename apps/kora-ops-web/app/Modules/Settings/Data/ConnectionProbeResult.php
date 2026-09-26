<?php

namespace App\Modules\Settings\Data;

use DateTimeImmutable;

final readonly class ConnectionProbeResult
{
    public function __construct(
        public string $provider,
        public string $status,
        public string $environment,
        public DateTimeImmutable $checkedAt,
        public int $durationMs,
        public string $safeMessage,
    ) {}

    public static function success(
        string $provider,
        string $environment,
        int $durationMs,
        string $message,
    ): self {
        return new self(
            provider: $provider,
            status: 'success',
            environment: $environment,
            checkedAt: new DateTimeImmutable,
            durationMs: $durationMs,
            safeMessage: $message,
        );
    }

    public static function failure(
        string $provider,
        string $environment,
        int $durationMs,
        string $message,
        string $status = 'failed',
    ): self {
        return new self(
            provider: $provider,
            status: $status,
            environment: $environment,
            checkedAt: new DateTimeImmutable,
            durationMs: $durationMs,
            safeMessage: $message,
        );
    }

    /** @return array{provider: string, status: string, environment: string, checkedAt: string, durationMs: int, safeMessage: string} */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'status' => $this->status,
            'environment' => $this->environment,
            'checkedAt' => $this->checkedAt->format(DATE_ATOM),
            'durationMs' => $this->durationMs,
            'safeMessage' => $this->safeMessage,
        ];
    }
}
