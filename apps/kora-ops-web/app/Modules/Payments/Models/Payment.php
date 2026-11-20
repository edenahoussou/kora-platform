<?php

namespace App\Modules\Payments\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Parcels\Models\ParcelCharge;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Support\Models\KoraModel;
use App\Modules\Wallet\Models\WalletTopUp;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property string|null $request_fingerprint
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property int|null $cash_received_amount
 * @property int|null $change_amount
 * @property array<string, mixed>|null $provider_payload
 * @property array<string, int>|null $wallet_funding
 */
class Payment extends KoraModel
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'reservation_id',
        'parcel_charge_id',
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
        'request_fingerprint',
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
        'wallet_funding',
    ];

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return HasOne<WalletTopUp, $this> */
    public function walletTopUp(): HasOne
    {
        return $this->hasOne(WalletTopUp::class);
    }

    public function providerDescription(): string
    {
        if ($this->parcel_charge_id !== null) {
            return "KORA COLIS {$this->uuid}";
        }

        if ($this->reservation !== null) {
            return "KORA BUS reservation {$this->reservation->uuid}";
        }

        return 'KORA BUS recharge '.($this->walletTopUp->uuid ?? $this->uuid);
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

    /** @return BelongsTo<ParcelCharge, $this> */
    public function parcelCharge(): BelongsTo
    {
        return $this->belongsTo(ParcelCharge::class);
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
            'wallet_funding' => 'array',
        ];
    }
}
