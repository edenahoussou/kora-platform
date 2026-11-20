<?php

namespace App\Modules\Fiscalization\Models;

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Fiscalization\Data\FiscalInvoiceDocumentKind;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Parcels\Models\ParcelCharge;
use App\Modules\Payments\Models\Payment;
use App\Modules\Support\Models\KoraModel;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property FiscalInvoiceStatus $status
 * @property FiscalInvoiceDocumentKind $document_kind
 * @property array<string, mixed>|null $source_snapshot
 * @property int $total_with_tax
 * @property Carbon|null $fiscalized_at
 * @property Carbon|null $requested_at
 */
class FiscalInvoice extends KoraModel
{
    protected $fillable = [
        'uuid',
        'reservation_id',
        'parcel_charge_id',
        'payment_id',
        'kora_reference',
        'mecef_uid',
        'emecf_invoice_id',
        'status',
        'document_kind',
        'original_fiscal_invoice_id',
        'requested_by_user_id',
        'credit_note_reason',
        'credit_note_idempotency_key',
        'source_snapshot',
        'requested_at',
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
            'document_kind' => FiscalInvoiceDocumentKind::class,
            'tax_snapshot' => 'array',
            'source_snapshot' => 'encrypted:array',
            'fiscalized_at' => 'datetime',
            'requested_at' => 'datetime',
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

    /** @return BelongsTo<ParcelCharge, $this> */
    public function parcelCharge(): BelongsTo
    {
        return $this->belongsTo(ParcelCharge::class);
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

    /** @return BelongsTo<self, $this> */
    public function originalFiscalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_fiscal_invoice_id');
    }

    /** @return HasMany<self, $this> */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'original_fiscal_invoice_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
