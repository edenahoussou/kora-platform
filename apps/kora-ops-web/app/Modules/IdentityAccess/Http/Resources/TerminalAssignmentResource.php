<?php

namespace App\Modules\IdentityAccess\Http\Resources;

use App\Modules\IdentityAccess\Models\TerminalAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TerminalAssignment
 */
class TerminalAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'assigned_user' => $this->assignedUser ? [
                'uuid' => $this->assignedUser->uuid,
                'name' => $this->assignedUser->name,
            ] : null,
            'agency' => $this->agency ? [
                'uuid' => $this->agency->uuid,
                'code' => $this->agency->code,
                'name' => $this->agency->name,
            ] : null,
            'assigned_by' => $this->assignedByUser ? [
                'uuid' => $this->assignedByUser->uuid,
                'name' => $this->assignedByUser->name,
            ] : null,
            'assigned_at' => $this->assigned_at->toISOString(),
            'unassigned_at' => $this->unassigned_at?->toISOString(),
            'reason' => $this->reason,
        ];
    }
}
