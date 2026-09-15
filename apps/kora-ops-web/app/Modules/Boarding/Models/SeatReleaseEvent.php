<?php

namespace App\Modules\Boarding\Models;

use App\Models\User;
use App\Modules\Boarding\Data\SeatReleaseEventStatus;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Support\Models\KoraModel;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $ticket_id
 * @property int $departure_id
 * @property int|null $seat_id
 * @property int $actual_stop_id
 * @property array<int, array<string, mixed>> $released_segments
 * @property int|null $terminal_p501_id
 * @property string $operation_uuid
 * @property Carbon $local_recorded_at
 * @property Carbon|null $server_recorded_at
 * @property int|null $created_by
 * @property SeatReleaseEventStatus $status
 * @property array<string, mixed>|null $conflict_payload
 */
class SeatReleaseEvent extends KoraModel
{
    protected $fillable = [
        'uuid',
        'ticket_id',
        'departure_id',
        'seat_id',
        'actual_stop_id',
        'released_segments',
        'terminal_p501_id',
        'operation_uuid',
        'local_recorded_at',
        'server_recorded_at',
        'created_by',
        'status',
        'conflict_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => SeatReleaseEventStatus::class,
            'released_segments' => 'array',
            'conflict_payload' => 'array',
            'local_recorded_at' => 'datetime',
            'server_recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<Departure, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    /**
     * @return BelongsTo<Seat, $this>
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    /**
     * @return BelongsTo<Stop, $this>
     */
    public function actualStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'actual_stop_id');
    }

    /**
     * @return BelongsTo<TerminalP501, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TerminalP501::class, 'terminal_p501_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
