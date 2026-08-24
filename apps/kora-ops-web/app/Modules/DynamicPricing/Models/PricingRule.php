<?php

namespace App\Modules\DynamicPricing\Models;

use App\Modules\DynamicPricing\Data\PricingRuleStatus;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
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
        'line_id',
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
            'type' => PricingRuleType::class,
            'conditions' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => PricingRuleStatus::class,
        ];
    }
}
