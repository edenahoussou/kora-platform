<?php

namespace App\Modules\IdentityAccess\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use function Illuminate\Support\enum_value;

/**
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'registration_origin' => $this->registration_origin ?? 'unknown',
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'birth_date' => $this->birth_date,
            'avatar_url' => $this->getFirstMediaUrl('avatar', 'thumb') ?: null,
            'status' => enum_value($this->status),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
