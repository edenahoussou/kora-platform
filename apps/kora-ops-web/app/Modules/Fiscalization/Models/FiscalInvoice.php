<?php

namespace App\Modules\Fiscalization\Models;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Support\Models\KoraModel;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property FiscalInvoiceStatus $status
 * @property int $total_with_tax
 * @property Carbon|null $fiscalized_at
 */
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
        'last_error',
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

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<EmecfInvoice, $this> */
    public function emecfInvoice(): BelongsTo
    {
        return $this->belongsTo(EmecfInvoice::class);
    }

    /** @return HasMany<FiscalSyncAttempt, $this> */
    public function syncAttempts(): HasMany
    {
        return $this->hasMany(FiscalSyncAttempt::class);
    }
}
