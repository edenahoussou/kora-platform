<?php

namespace App\Modules\BusNetwork\Models;

use App\Models\User;
use App\Modules\BusNetwork\Data\SeatStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon|null $created_at */
class SeatStatusEvent extends KoraModel
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'seat_id',
        'bus_id',
        'from_status',
        'to_status',
        'reason',
        'changed_by',
        'created_at',
    ];

    /**
     * @return BelongsTo<Seat, $this>
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    /**
     * @return BelongsTo<Bus, $this>
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return [
            'from_status' => SeatStatus::class,
            'to_status' => SeatStatus::class,
            'created_at' => 'datetime',
        ];
    }
}
