<?php

namespace App\Modules\IdentityAccess\Actions;

class HashDeviceFingerprintAction
{
    public function handle(string $fingerprint): string
    {
        return hash('sha256', trim($fingerprint));
    }
}
