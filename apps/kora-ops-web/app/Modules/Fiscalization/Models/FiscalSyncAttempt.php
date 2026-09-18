<?php

namespace App\Modules\Fiscalization\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $attempt_number
 * @property Carbon $attempted_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $response_payload
 * @property array<string, mixed>|null $response_payload_encrypted
 */
class FiscalSyncAttempt extends KoraModel
{
    protected $fillable = [
        'uuid',
        'fiscal_invoice_id',
        'operation',
        'attempt_number',
        'request_hash',
        'response_code',
        'error_code',
        'status',
        'attempted_at',
        'completed_at',
        'response_payload',
        'response_payload_encrypted',
        'error_message',
    ];

    protected $hidden = ['response_payload_encrypted'];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'attempted_at' => 'datetime',
            'completed_at' => 'datetime',
            'response_payload' => 'array',
            'response_payload_encrypted' => 'encrypted:array',
        ];
    }

    /** @return BelongsTo<FiscalInvoice, $this> */
    public function fiscalInvoice(): BelongsTo
    {
        return $this->belongsTo(FiscalInvoice::class);
    }
}
