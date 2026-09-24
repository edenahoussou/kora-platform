<?php

namespace App\Modules\DynamicPricing\Models;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Data\FareDirection;
use App\Modules\DynamicPricing\Data\PricingRuleScope;
use App\Modules\DynamicPricing\Data\PricingRuleStatus;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property PricingRuleScope $scope_type
 * @property FareDirection $direction
 * @property PricingRuleType $type
 * @property array<string, mixed>|null $conditions
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property PricingRuleStatus $status
 */
class PricingRule extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'scope_type',
        'line_id',
        'origin_stop_id',
        'destination_stop_id',
        'direction',
        'type',
        'conditions',
        'percentage',
        'priority',
        'starts_at',
        'ends_at',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => PricingRuleScope::class,
            'direction' => FareDirection::class,
            'type' => PricingRuleType::class,
            'conditions' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => PricingRuleStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // Filet de sécurité pour les créations directes (seeders, tests) qui ne
        // renseignent pas scope_type : la portée se déduit des colonnes.
        static::saving(function (PricingRule $rule): void {
            $rule->scope_type ??= $rule->effectiveScope();
        });
    }

    /**
     * @return BelongsTo<Line, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * @return BelongsTo<Stop, $this>
     */
    public function originStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'origin_stop_id');
    }

    /**
     * @return BelongsTo<Stop, $this>
     */
    public function destinationStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'destination_stop_id');
    }

    /**
     * Portée déduite des colonnes : segment si les deux arrêts sont définis,
     * sinon ligne si une ligne est rattachée, sinon réseau.
     */
    public function effectiveScope(): PricingRuleScope
    {
        if ($this->origin_stop_id !== null && $this->destination_stop_id !== null) {
            return PricingRuleScope::Segment;
        }

        return $this->line_id !== null ? PricingRuleScope::Line : PricingRuleScope::Network;
    }

    /**
     * Le trajet demandé (segment O/D de la ligne du départ) entre-t-il dans le
     * périmètre de la règle ? Le segment est comparé par paire d'arrêts, sans
     * tenir compte du sens — la direction est filtrée à part.
     */
    public function coversJourney(int $lineId, Stop $originStop, Stop $destinationStop): bool
    {
        return match ($this->scope_type ?? $this->effectiveScope()) {
            PricingRuleScope::Network => true,
            PricingRuleScope::Line => $this->line_id === $lineId,
            PricingRuleScope::Segment => $this->line_id === $lineId
                && $this->matchesSegment($originStop, $destinationStop),
        };
    }

    /**
     * Le sens de circulation du trajet respecte-t-il la direction de la règle ?
     * Aller = ordre croissant des arrêts sur la ligne, retour = décroissant.
     */
    public function matchesDirection(Stop $originStop, Stop $destinationStop): bool
    {
        return match ($this->direction ?? FareDirection::Both) {
            FareDirection::Both => true,
            FareDirection::Outbound => $originStop->stop_order < $destinationStop->stop_order,
            FareDirection::Return => $originStop->stop_order > $destinationStop->stop_order,
        };
    }

    private function matchesSegment(Stop $originStop, Stop $destinationStop): bool
    {
        if ($this->origin_stop_id === null || $this->destination_stop_id === null) {
            return false;
        }

        $ruleEndpoints = [$this->origin_stop_id, $this->destination_stop_id];
        $journeyEndpoints = [$originStop->getKey(), $destinationStop->getKey()];
        sort($ruleEndpoints);
        sort($journeyEndpoints);

        return $ruleEndpoints === $journeyEndpoints;
    }
}
