<?php

namespace App\Modules\IdentityAccess\Exceptions;

use RuntimeException;

class AuthSuspendedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Le compte est suspendu.');
    }
}
