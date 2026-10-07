<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Data\PurchaseType;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyCreditHold;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\DynamicPricing\Models\Promotion;
use App\Modules\Fiscalization\Data\FiscalInvoiceDocumentKind;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Support\Models\KoraModel;
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property ReservationStatus $status
 * @property ReservationChannel|null $channel
 * @property Carbon|null $expires_at
 * @property TicketIssuanceStatus $ticket_issuance_status
 * @property Carbon|null $ticket_issuance_started_at
 * @property Carbon|null $ticket_issued_at
 * @property array<string, mixed>|null $total_snapshot
 * @property array<string, mixed>|null $draft_data
 * @property array<string, mixed>|null $payer_snapshot
 * @property PayerType $payer_type
 * @property PurchaseType $purchase_type
 * @property int|null $payer_user_id
 * @property int|null $company_account_id
 * @property int|null $created_by_user_id
 * @property int $promotion_discount_amount
 */
class Reservation extends KoraModel
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'reference',
        'selling_agency_id',
        'created_by_user_id',
        'payer_user_id',
        'payer_type',
        'company_account_id',
        'purchase_type',
        'group_size',
        'payer_snapshot',
        'price_quote_id',
        'itinerary_quote_id',
        'promotion_id',
        'promotion_discount_amount',
        'status',
        'ticket_issuance_status',
        'ticket_issuance_error',
        'ticket_issuance_started_at',
        'ticket_issued_at',
        'total_amount',
        'currency',
        'channel',
        'idempotency_key',
        'expires_at',
        'total_snapshot',
        'draft_data',
    ];

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation): void {
            if ($reservation->reference) {
                return;
            }

            do {
                $reference = 'KBR-'.Str::upper(Str::random(8));
            } while (self::query()->where('reference', $reference)->exists());

            $reservation->reference = $reference;
        });

        static::updating(function (Reservation $reservation): void {
            if ($reservation->isDirty('reference') && $reservation->getOriginal('reference') !== null) {
                $reservation->reference = $reservation->getOriginal('reference');
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    /** @return BelongsTo<CompanyAccount, $this> */
    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class);
    }

    /** @return HasMany<CompanyCreditHold, $this> */
    public function companyCreditHolds(): HasMany
    {
        return $this->hasMany(CompanyCreditHold::class);
    }

    /** @return BelongsTo<ItineraryQuote, $this> */
    public function itineraryQuote(): BelongsTo
    {
        return $this->belongsTo(ItineraryQuote::class);
    }

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** @return BelongsTo<PriceQuote, $this> */
    public function priceQuote(): BelongsTo
    {
        return $this->belongsTo(PriceQuote::class);
    }

    /**
     * Ordered legs of this itinerary (by leg_order).
     *
     * @return HasMany<ReservationSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(ReservationSegment::class)->orderBy('leg_order');
    }

    /**
     * @return HasMany<ReservationPassenger, $this>
     */
    public function passengers(): HasMany
    {
        return $this->hasMany(ReservationPassenger::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<PaymentRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return HasOne<FiscalInvoice, $this>
     */
    public function fiscalInvoice(): HasOne
    {
        // Une réservation peut aussi porter des avoirs : la facture « de »
        // la réservation reste sa facture de vente.
        return $this->hasOne(FiscalInvoice::class)
            ->where('document_kind', FiscalInvoiceDocumentKind::Sale->value);
    }

    /** @return HasOne<AssistedBooking, $this> */
    public function assistedBooking(): HasOne
    {
        return $this->hasOne(AssistedBooking::class);
    }

    /** @return BelongsTo<Agency, $this> */
    public function sellingAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'selling_agency_id')->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'ticket_issuance_status' => TicketIssuanceStatus::class,
            'channel' => ReservationChannel::class,
            'payer_type' => PayerType::class,
            'purchase_type' => PurchaseType::class,
            'group_size' => 'integer',
            'expires_at' => 'datetime',
            'ticket_issuance_started_at' => 'datetime',
            'ticket_issued_at' => 'datetime',
            'total_snapshot' => 'array',
            'draft_data' => 'array',
            'payer_snapshot' => 'array',
        ];
    }

    /**
     * Departure of the first leg, replacing the former direct departure_id relation.
     * Use this for receipts, redirects, and any place that previously read reservation->departure.
     */
    public function primaryDeparture(): ?Departure
    {
        $first = $this->segments()->orderBy('leg_order')->first();

        return $first?->departure;
    }
}
