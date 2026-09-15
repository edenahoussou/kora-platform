<?php

namespace App\Modules\Sync\Models;

use App\Modules\Support\Models\KoraModel;
use App\Modules\Sync\Data\SyncOperationStatus;
use App\Modules\Sync\Data\SyncOperationType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $sync_batch_id
 * @property string $operation_uuid Idempotence — fourni par le terminal, unique.
 * @property SyncOperationType $operation_type
 * @property Carbon $local_recorded_at
 * @property Carbon|null $server_received_at
 * @property SyncOperationStatus $status
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $result
 */
class SyncOperation extends KoraModel
{
    protected $fillable = [
        'uuid',
        'sync_batch_id',
        'operation_uuid',
        'operation_type',
        'local_recorded_at',
        'server_received_at',
        'status',
        'payload',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'operation_type' => SyncOperationType::class,
            'status' => SyncOperationStatus::class,
            'local_recorded_at' => 'datetime',
            'server_received_at' => 'datetime',
            'payload' => 'array',
            'result' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SyncBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(SyncBatch::class, 'sync_batch_id');
    }
}
