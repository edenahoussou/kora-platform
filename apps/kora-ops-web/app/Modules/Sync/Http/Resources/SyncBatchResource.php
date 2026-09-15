<?php

namespace App\Modules\Sync\Http\Resources;

use App\Modules\Sync\Models\SyncBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use function Illuminate\Support\enum_value;

/**
 * @mixin SyncBatch
 */
class SyncBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => enum_value($this->status),
            'submittedAt' => $this->submitted_at->toISOString(),
            'processedAt' => $this->processed_at->toISOString(),
            'summary' => $this->summary,
            'operations' => $this->whenLoaded('operations', fn () => $this->operations->map(fn ($op) => [
                'operationUuid' => $op->operation_uuid,
                'operationType' => enum_value($op->operation_type),
                'status' => enum_value($op->status),
                'result' => $op->result,
            ])->values()),
        ];
    }
}
