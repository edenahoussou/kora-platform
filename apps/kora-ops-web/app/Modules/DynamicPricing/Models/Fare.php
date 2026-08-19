<?php

namespace App\Modules\DynamicPricing\Models;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Data\FareStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_until
 */
class Fare extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'line_id',
        'service_class_id',
        'origin_stop_id',
        'destination_stop_id',
        'base_amount',
        'currency',
        'effective_from',
        'effective_until',
        'status',
        'created_by',
    ];

    /**
     * @return BelongsTo<Line, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * @return BelongsTo<ServiceClass, $this>
     */
    public function serviceClass(): BelongsTo
    {
        return $this->belongsTo(ServiceClass::class);
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

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'status' => FareStatus::class,
        ];
    }
}
