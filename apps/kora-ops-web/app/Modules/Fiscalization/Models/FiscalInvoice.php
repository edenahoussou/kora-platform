<?php

namespace App\Modules\Fiscalization\Models;

use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Support\Models\KoraModel;

class FiscalInvoice extends KoraModel
{
    protected $fillable = [
        'uuid',
        'reservation_id',
        'payment_id',
        'kora_reference',
        'mecef_uid',
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
}
