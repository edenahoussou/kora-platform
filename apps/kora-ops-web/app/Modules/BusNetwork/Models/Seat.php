<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\BusNetwork\Data\SeatStatus;
use App\Modules\BusNetwork\Data\SeatType;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seat extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'bus_id',
        'code',
        'type',
        'sellable',
        'status',
    ];

    /**
     * @return BelongsTo<Bus, $this>
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    protected function casts(): array
    {
        return [
            'type' => SeatType::class,
            'sellable' => 'boolean',
            'status' => SeatStatus::class,
        ];
    }
}
