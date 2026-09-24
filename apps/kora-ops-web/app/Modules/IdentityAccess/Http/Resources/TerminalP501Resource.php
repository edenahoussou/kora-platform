<?php

namespace App\Modules\IdentityAccess\Http\Resources;

use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use function Illuminate\Support\enum_value;

/**
 * @mixin TerminalP501
 */
class TerminalP501Resource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'serial_number' => $this->serial_number,
            'type' => enum_value($this->type),
            'hardware_model' => $this->hardware_model,
            'os_version' => $this->os_version,
            'app_version' => $this->app_version,
            'status' => enum_value($this->status),
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser ? [
                'uuid' => $this->assignedUser->uuid,
                'name' => $this->assignedUser->name,
            ] : null),
            'agency' => $this->whenLoaded('agency', fn () => $this->agency ? [
                'uuid' => $this->agency->uuid,
                'code' => $this->agency->code,
                'name' => $this->agency->name,
            ] : null),
            'last_sync_at' => $this->last_sync_at?->toISOString(),
            'last_handshake_at' => $this->last_handshake_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
