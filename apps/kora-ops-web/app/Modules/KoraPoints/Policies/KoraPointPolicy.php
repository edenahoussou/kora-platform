<?php

namespace App\Modules\KoraPoints\Policies;

use App\Models\User;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Services\KoraPointAccess;

class KoraPointPolicy
{
    public function __construct(private readonly KoraPointAccess $access) {}

    public function view(User $user, KoraPoint $point): bool
    {
        return $this->access->viewPoint($user, $point);
    }
}
