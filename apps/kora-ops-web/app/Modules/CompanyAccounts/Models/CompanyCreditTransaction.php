<?php

namespace App\Modules\CompanyAccounts\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Data\CompanyCreditTransactionType;
use App\Modules\Payments\Models\Payment;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property CompanyCreditTransactionType $type */
class CompanyCreditTransaction extends KoraModel
{
    use HasPublicUuid;

    protected $fillable = ['uuid', 'company_account_id', 'reservation_id', 'payment_id', 'type', 'amount', 'period_key', 'reference', 'metadata', 'created_by'];

    protected function casts(): array
    {
        return ['type' => CompanyCreditTransactionType::class, 'amount' => 'integer', 'metadata' => 'array'];
    }

    /** @return BelongsTo<CompanyAccount, $this> */
    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class);
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
