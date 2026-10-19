<?php

namespace App\Modules\Notifications\Services\Sms;

use RuntimeException;
use Throwable;

final class ConexteoSmsException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromStatus(int $status): self
    {
        return new self("Conexteo a refusé l’envoi du SMS (HTTP {$status}).", $status);
    }

    public static function unavailable(?Throwable $previous = null): self
    {
        return new self('Conexteo est indisponible pour le moment.', null, $previous);
    }

    public function status(): ?int
    {
        return $this->status;
    }

    public function shouldRetry(): bool
    {
        return $this->status === null
            || $this->status === 408
            || $this->status === 429
            || $this->status >= 500;
    }
}
