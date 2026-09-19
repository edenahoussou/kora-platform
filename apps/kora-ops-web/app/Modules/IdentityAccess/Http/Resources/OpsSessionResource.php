<?php

namespace App\Modules\IdentityAccess\Http\Resources;

use App\Modules\IdentityAccess\Models\TerminalSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TerminalSession
 */
class OpsSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'state' => 'active',
            'opened_at' => $this->started_at->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'renewable_until' => $this->absolute_expires_at?->toISOString(),
            'renewed_at' => $this->renewed_at?->toISOString(),
            'renewal_count' => $this->renewal_count,
        ];
    }
}
