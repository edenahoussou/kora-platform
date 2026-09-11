<?php

namespace App\Modules\IdentityAccess\Exceptions;

use RuntimeException;

class UserNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Utilisateur introuvable.');
    }
}
