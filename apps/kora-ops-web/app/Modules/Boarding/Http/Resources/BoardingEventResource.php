<?php

namespace App\Modules\Boarding\Http\Resources;

use App\Modules\Boarding\Models\BoardingEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use function Illuminate\Support\enum_value;

/**
 * @mixin BoardingEvent
 */
class BoardingEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $details = $this->conflict_payload;

        return [
            'uuid' => $this->uuid,
            'status' => enum_value($this->status),
            'ticketUuid' => $this->ticket?->uuid,
            'departureUuid' => $this->departure?->uuid,
            'operationUuid' => $this->operation_uuid,
            // ✅ CORRECTION : Suppression de l'opérateur nullsafe (?->) sur les dates castées
            'localRecordedAt' => $this->local_recorded_at->toISOString(),
            'serverRecordedAt' => $this->server_recorded_at->toISOString(),
            'code' => $details['code'] ?? null,
            'message' => $details['message'] ?? null,
        ];
    }
}
