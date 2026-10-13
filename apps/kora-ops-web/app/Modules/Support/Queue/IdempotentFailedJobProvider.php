<?php

namespace App\Modules\Support\Queue;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;

/**
 * Tolère un second failJob() sur le même uuid de payload : Redis peut
 * re-livrer un job réservé (> retry_after) alors qu'il tourne encore.
 */
class IdempotentFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    /** @return string|null */
    public function log($connection, $queue, $payload, $exception)
    {
        try {
            return parent::log($connection, $queue, $payload, $exception);
        } catch (UniqueConstraintViolationException) {
            return json_decode($payload, true)['uuid'] ?? null;
        }
    }
}
