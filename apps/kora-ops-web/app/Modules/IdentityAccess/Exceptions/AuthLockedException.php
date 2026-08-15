<?php

namespace App\Modules\IdentityAccess\Exceptions;

use Carbon\CarbonInterface;
use RuntimeException;

class AuthLockedException extends RuntimeException
{
    public function __construct(
        public readonly ?CarbonInterface $lockedUntil = null,
    ) {
        parent::__construct('Le compte est temporairement bloqué.');
    }
}
