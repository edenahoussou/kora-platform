<?php

namespace App\Modules\FleetLite\Models;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Departures\Models\Departure;
use App\Modules\FleetLite\Data\IncidentStatus;
use App\Modules\FleetLite\Data\IncidentType;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Signalement terrain (panne, retard, accident, sécurité, autre) envoyé par
 * un convoyeur depuis OPS Mobile.
 *
 * @property IncidentType $type
 * @property IncidentStatus $status
 * @property string $description
 * @property Carbon|null $occurred_at
 * @property int|null $created_by
 * @property Departure|null $departure
 */
class Incident extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'departure_id',
        'bus_id',
        'type',
        'description',
        'latitude',
        'longitude',
        'occurred_at',
        'created_by',
        'terminal_p501_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'status' => IncidentStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Departure, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<TerminalP501, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TerminalP501::class, 'terminal_p501_id');
    }
}
