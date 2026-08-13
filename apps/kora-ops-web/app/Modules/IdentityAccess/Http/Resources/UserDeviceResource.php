<?php

namespace App\Modules\IdentityAccess\Http\Resources;

use App\Modules\IdentityAccess\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use function Illuminate\Support\enum_value;

/**
 * @mixin UserDevice
 */
class UserDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'platform' => enum_value($this->platform),
            'trusted_at' => $this->trusted_at?->toISOString(),
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'status' => enum_value($this->status),
        ];
    }
}
