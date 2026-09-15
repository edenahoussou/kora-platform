<?php

namespace App\Modules\Boarding\Models;

use App\Modules\Boarding\Data\BoardingEventStatus;
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
 * @property int|null $terminal_p501_id
 * @property string $operation_uuid
 * @property Carbon $local_recorded_at
 * @property Carbon|null $server_recorded_at
 * @property BoardingEventStatus $status
 * @property array<string, mixed>|null $conflict_payload
 */
class BoardingEvent extends KoraModel
{
    protected $fillable = [
        'uuid',
        'ticket_id',
        'departure_id',
        'terminal_p501_id',
        'operation_uuid',
        'local_recorded_at',
        'server_recorded_at',
        'status',
        'conflict_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => BoardingEventStatus::class,
            'local_recorded_at' => 'datetime',
            'server_recorded_at' => 'datetime',
            'conflict_payload' => 'array',
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
     * @return BelongsTo<TerminalP501, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TerminalP501::class, 'terminal_p501_id');
    }
}
