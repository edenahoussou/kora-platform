<?php

namespace App\Modules\Notifications\Services\WhatsApp;

use RuntimeException;

/**
 * WAHA 463 (Reachout Timelock) / 475 (Message Capping).
 *
 * @see https://waha.devlike.pro/docs/overview/how-to-avoid-blocking/
 */
class WahaReachoutBlockedException extends RuntimeException
{
    public function __construct(public readonly ?int $wahaCode = null, string $message = '')
    {
        parent::__construct($message ?: "WhatsApp a temporairement restreint l'envoi (code {$wahaCode}).");
    }
}
