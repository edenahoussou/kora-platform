<?php

namespace App\Modules\IdentityAccess\Http\Resources;

use App\Models\User;
use App\Modules\IdentityAccess\Services\OpsSessionGuard;
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
            // OPS Mobile : rôles (codes système) et obligation de terminal
            // P501 — le chauffeur seul en est dispensé, sauf si
            // OPS_FORCE_TERMINAL_FOR_ALL=true (voir OpsSessionGuard).
            'roles' => $this->getRoleNames()->values(),
            'requires_terminal' => app(OpsSessionGuard::class)->requiresTerminal($this->resource),
        ];
    }
}
