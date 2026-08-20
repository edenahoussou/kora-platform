<?php

namespace App\Modules\IdentityAccess\Exceptions;

use RuntimeException;

class OAuthEmailRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Le fournisseur OAuth n\'a pas retourné d\'adresse email. '
            .'Un email est obligatoire pour créer un compte KORA.',
        );
    }
}
