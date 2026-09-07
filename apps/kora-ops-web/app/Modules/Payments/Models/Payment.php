<?php

namespace App\Modules\Payments\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $amount
 * @property PaymentMethod|null $method
 * @property MobileMoneyNetwork|null $mobile_money_network
 * @property PaymentStatus $status
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $last_verified_at
 * @property string|null $provider_transaction_id
 * @property string|null $provider_currency
 * @property int|null $provider_amount
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property int|null $cash_received_amount
 * @property int|null $change_amount
 * @property array<string, mixed>|null $provider_payload
 */
class Payment extends KoraModel
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'reservation_id',
        'method',
        'agency_payment_account_id',
        'cash_session_id',
        'cash_ledger_id',
        'provider',
        'provider_reference',
        'provider_transaction_id',
        'provider_currency',
        'provider_amount',
        'idempotency_key',
        'amount',
        'mobile_money_network',
        'payer_phone',
        'payer_name',
        'cash_received_amount',
        'change_amount',
        'verified_by',
        'verified_at',
        'last_verified_at',
        'failure_code',
        'failure_message',
        'currency',
        'status',
        'confirmed_at',
        'provider_payload',
    ];

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<AgencyPaymentAccount, $this>
     */
    public function agencyPaymentAccount(): BelongsTo
    {
        return $this->belongsTo(AgencyPaymentAccount::class);
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /**
     * @return BelongsTo<CashLedger, $this>
     */
    public function cashLedger(): BelongsTo
    {
        return $this->belongsTo(CashLedger::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return HasMany<PaymentRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'mobile_money_network' => MobileMoneyNetwork::class,
            'amount' => 'integer',
            'provider_amount' => 'integer',
            'status' => PaymentStatus::class,
            'cash_received_amount' => 'integer',
            'change_amount' => 'integer',
            'confirmed_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }
}
