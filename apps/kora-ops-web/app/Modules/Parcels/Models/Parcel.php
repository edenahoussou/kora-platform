<?php

namespace App\Modules\Parcels\Models;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Parcels\Data\ParcelInspectionCategory;
use App\Modules\Payments\Models\Payment;
use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $available_at
 * @property CarbonImmutable|null $inspected_at
 * @property ParcelInspectionCategory|null $inspection_category
 * @property CarbonImmutable|null $return_due_at
 * @property CarbonImmutable|null $return_handed_over_at
 */
class Parcel extends KoraModel
{
    protected $fillable = ['reference', 'corridor_id', 'deposit_hub_id', 'selling_agency_id', 'idempotency_key', 'intake_fingerprint', 'sender_user_id', 'custody_hub_id', 'status', 'sender_name', 'sender_phone', 'recipient_name', 'recipient_phone', 'recipient_phone_hash', 'contents', 'declared_grams', 'weight_grams', 'inspection_category', 'inspection_refusal_reason', 'inspected_at', 'inspection_hub_id', 'expires_at', 'available_at', 'return_due_at', 'return_handed_over_at', 'handoff_hash', 'handoff_attempts', 'share_hash'];

    protected $hidden = ['id', 'sender_name', 'sender_phone', 'recipient_name', 'recipient_phone', 'recipient_phone_hash', 'contents', 'handoff_hash', 'share_hash'];

    protected function casts(): array
    {
        return [
            'sender_name' => 'encrypted',
            'sender_phone' => 'encrypted',
            'recipient_name' => 'encrypted',
            'recipient_phone' => 'encrypted',
            'contents' => 'encrypted',
            'inspection_category' => ParcelInspectionCategory::class,
            'inspected_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'available_at' => 'immutable_datetime',
            'return_due_at' => 'immutable_datetime',
            'return_handed_over_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ParcelCorridor, $this> */
    public function corridor(): BelongsTo
    {
        return $this->belongsTo(ParcelCorridor::class);
    }

    /** @return BelongsTo<ParcelHub, $this> */
    public function custodyHub(): BelongsTo
    {
        return $this->belongsTo(ParcelHub::class);
    }

    /** @return BelongsTo<ParcelHub, $this> */
    public function depositHub(): BelongsTo
    {
        return $this->belongsTo(ParcelHub::class, 'deposit_hub_id');
    }

    /** @return BelongsTo<ParcelHub, $this> */
    public function inspectionHub(): BelongsTo
    {
        return $this->belongsTo(ParcelHub::class, 'inspection_hub_id');
    }

    /** @return BelongsTo<Agency, $this> */
    public function sellingAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'selling_agency_id');
    }

    /** @return HasMany<ParcelAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(ParcelAssignment::class);
    }

    /** @return HasMany<ParcelCharge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(ParcelCharge::class);
    }

    /** @return HasManyThrough<Payment, ParcelCharge, $this> */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, ParcelCharge::class);
    }

    /** @return HasMany<ParcelEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ParcelEvent::class);
    }

    /** @return HasMany<ParcelIncident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(ParcelIncident::class);
    }

    /** @param array<string, mixed> $details */
    public function record(
        string $kind,
        ?User $actor = null,
        ?ParcelHub $hub = null,
        ?ParcelAssignment $assignment = null,
        ?TerminalP501 $terminal = null,
        ?CarbonInterface $observedAt = null,
        array $details = [],
        string $channel = 'ops',
    ): ParcelEvent {
        return $this->events()->create([
            'kind' => $kind,
            'operation_uuid' => $details['operation_uuid'] ?? null,
            'actor_id' => $actor?->id,
            'hub_id' => $hub?->id,
            'assignment_id' => $assignment?->id,
            'terminal_id' => $terminal?->id,
            'observed_at' => $observedAt ?? now(),
            'details' => $details,
            'channel' => $terminal === null ? $channel : 'p501',
        ]);
    }
}
