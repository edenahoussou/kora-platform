<?php

namespace App\Modules\Fiscalization\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalTestInvoiceRun extends KoraModel
{
    protected $fillable = [
        'uuid',
        'case_number',
        'requested_by_user_id',
        'source_run_id',
        'emecf_invoice_id',
        'status',
        'request_snapshot',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'request_snapshot' => 'encrypted:array',
            'case_number' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<self, $this> */
    public function sourceRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_run_id');
    }

    /** @return BelongsTo<EmecfInvoice, $this> */
    public function emecfInvoice(): BelongsTo
    {
        return $this->belongsTo(EmecfInvoice::class);
    }
}
