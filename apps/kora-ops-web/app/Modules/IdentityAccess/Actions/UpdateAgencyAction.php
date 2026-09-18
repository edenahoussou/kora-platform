<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Models\Agency;

class UpdateAgencyAction
{
    /**
     * @param  array{name: string, city: string, country: string, call_phone: string|null, whatsapp_phone: string|null, head_user_id: int|null, status: string}  $attributes
     */
    public function execute(Agency $agency, array $attributes): Agency
    {
        $agency->update($attributes);

        return $agency->refresh();
    }
}
