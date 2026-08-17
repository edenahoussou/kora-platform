<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Models\Agency;

class CreateAgencyAction
{
    /**
     * @param  array{code: string, name: string, city: string, country: string, head_user_id: int|null, status: string}  $attributes
     */
    public function execute(array $attributes): Agency
    {
        return Agency::query()->create($attributes);
    }
}
