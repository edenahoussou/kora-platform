<?php

namespace App\Modules\DynamicPricing\Models;

use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Data\PromotionBenefitType;
use App\Modules\DynamicPricing\Data\PromotionChannel;
use App\Modules\DynamicPricing\Data\PromotionScope;
use App\Modules\DynamicPricing\Data\PromotionStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Offre commerciale déclenchée par la saisie d'un code au moment du devis.
 *
 * Distincte de la règle de tarification dynamique (variation automatique
 * pilotée par remplissage/délai) et de la remise groupe (volume) : la
 * promotion est une campagne ciblée, jamais rétroactive sur les ventes
 * déjà confirmées.
 *
 * @property PromotionBenefitType $benefit_type
 * @property PromotionScope $scope_type
 * @property PromotionChannel|null $channel
 * @property PromotionStatus $status
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 */
class Promotion extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'benefit_type',
        'benefit_value',
        'scope_type',
        'channel',
        'line_id',
        'origin_stop_id',
        'destination_stop_id',
        'priority',
        'starts_at',
        'ends_at',
        'status',
        'max_uses',
        'used_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'benefit_type' => PromotionBenefitType::class,
            'benefit_value' => 'decimal:4',
            'scope_type' => PromotionScope::class,
            'channel' => PromotionChannel::class,
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => PromotionStatus::class,
            'max_uses' => 'integer',
            'used_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Line, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /** @return BelongsTo<Stop, $this> */
    public function originStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'origin_stop_id');
    }

    /** @return BelongsTo<Stop, $this> */
    public function destinationStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'destination_stop_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<PromotionRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    /**
     * La promotion peut-elle déclencher une remise à l'instant donné ?
     * Réservée aux promotions actives dans leur période d'application.
     */
    public function isEffective(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->status === PromotionStatus::Active
            && $this->starts_at->lte($at)
            && ($this->ends_at === null || $this->ends_at->gte($at));
    }

    public function hasUsesLeft(): bool
    {
        return $this->max_uses === null || $this->used_count < $this->max_uses;
    }

    /**
     * Statut affiché dans les écrans : « planifiée » et « expirée » sont
     * dérivés de la période d'une promotion active — jamais stockés.
     */
    public function displayStatus(?Carbon $at = null): string
    {
        if ($this->status !== PromotionStatus::Active) {
            return $this->status->value;
        }

        $at ??= now();

        if ($this->starts_at->gt($at)) {
            return 'scheduled';
        }

        if ($this->ends_at !== null && $this->ends_at->lt($at)) {
            return 'expired';
        }

        return PromotionStatus::Active->value;
    }

    /**
     * Empreinte du périmètre pour la détection de conflits : deux promotions
     * de même signature se concurrencent sur le même terrain d'application.
     */
    public function scopeSignature(): string
    {
        $channel = $this->channel;

        return implode(':', [
            $this->scope_type->value,
            $channel instanceof PromotionChannel ? $channel->value : '-',
            $this->line_id ?? '-',
            $this->origin_stop_id ?? '-',
            $this->destination_stop_id ?? '-',
        ]);
    }

    /**
     * La jambe (ligne + segment O/D) et le canal de la vente sont-ils
     * couverts par le périmètre de la promotion ?
     */
    public function coversLeg(int $lineId, int $originStopId, int $destinationStopId, ?ReservationChannel $channel): bool
    {
        return match ($this->scope_type) {
            PromotionScope::Network => true,
            PromotionScope::Channel => $channel !== null && $this->channel?->covers($channel),
            PromotionScope::Line => $this->line_id === $lineId,
            PromotionScope::Segment => $this->line_id === $lineId
                && $this->origin_stop_id === $originStopId
                && $this->destination_stop_id === $destinationStopId,
        };
    }

    /**
     * Remise unitaire (par passager) pour une jambe donnée, en FCFA.
     * Les avantages à montant fixe ne sont pas déclinés par jambe : ils se
     * déduisent une fois du total de la réservation.
     */
    public function legDiscount(int $legAmount): int
    {
        if ($this->benefit_type !== PromotionBenefitType::Percentage) {
            return 0;
        }

        return (int) round($legAmount * (float) $this->benefit_value / 100);
    }
}
