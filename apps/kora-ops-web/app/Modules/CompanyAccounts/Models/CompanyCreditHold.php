<?php

namespace App\Modules\CompanyAccounts\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Data\CompanyCreditHoldStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CompanyCreditHoldStatus $status
 * @property Carbon $expires_at
 */
class CompanyCreditHold extends KoraModel
{
    use HasPublicUuid;

    protected $fillable = ['uuid', 'company_account_id', 'reservation_id', 'amount', 'period_key', 'status', 'expires_at', 'released_at', 'converted_at'];

    protected function casts(): array
    {
        return ['status' => CompanyCreditHoldStatus::class, 'amount' => 'integer', 'expires_at' => 'datetime', 'released_at' => 'datetime', 'converted_at' => 'datetime'];
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
}
