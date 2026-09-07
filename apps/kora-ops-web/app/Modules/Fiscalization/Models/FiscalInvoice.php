<?php

namespace App\Modules\Fiscalization\Models;

use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Support\Models\KoraModel;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalInvoice extends KoraModel
{
    protected $fillable = [
        'uuid',
        'reservation_id',
        'payment_id',
        'kora_reference',
        'mecef_uid',
        'emecf_invoice_id',
        'status',
        'total_without_tax',
        'total_tax',
        'total_with_tax',
        'tax_snapshot',
        'fiscalized_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FiscalInvoiceStatus::class,
            'tax_snapshot' => 'array',
            'fiscalized_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmecfInvoice, $this> */
    public function emecfInvoice(): BelongsTo
    {
        return $this->belongsTo(EmecfInvoice::class);
    }
}
