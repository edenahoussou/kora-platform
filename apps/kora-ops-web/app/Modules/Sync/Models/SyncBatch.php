<?php

namespace App\Modules\Sync\Models;

use App\Models\User;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Support\Models\KoraModel;
use App\Modules\Sync\Data\SyncBatchStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid Fourni par le client (idempotence du lot) — pas auto-généré.
 * @property int|null $terminal_p501_id
 * @property int|null $submitted_by
 * @property SyncBatchStatus $status
 * @property Carbon $submitted_at
 * @property Carbon|null $processed_at
 * @property array<string, mixed>|null $summary
 */
class SyncBatch extends KoraModel
{
    protected $fillable = [
        'uuid',
        'terminal_p501_id',
        'submitted_by',
        'status',
        'submitted_at',
        'processed_at',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => SyncBatchStatus::class,
            'submitted_at' => 'datetime',
            'processed_at' => 'datetime',
            'summary' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TerminalP501, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TerminalP501::class, 'terminal_p501_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return HasMany<SyncOperation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(SyncOperation::class);
    }
}
