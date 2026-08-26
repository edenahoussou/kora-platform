<?php

namespace App\Modules\Ticketing\Models;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Support\Models\KoraModel;
use App\Modules\Ticketing\Data\TicketStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property TicketStatus $status
 * @property Carbon|null $issued_at
 */
class Ticket extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'reservation_id',
        'reservation_passenger_id',
        'qr_code',
        'short_code',
        'status',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ReservationPassenger, $this>
     */
    public function reservationPassenger(): BelongsTo
    {
        return $this->belongsTo(ReservationPassenger::class);
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
