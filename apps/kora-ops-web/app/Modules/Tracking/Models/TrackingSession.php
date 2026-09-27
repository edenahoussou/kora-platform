<?php

namespace App\Modules\Tracking\Models;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Support\Models\KoraModel;
use App\Modules\Tracking\Data\TrackingPositionQuality;
use App\Modules\Tracking\Data\TrackingPositionStatus;
use App\Modules\Tracking\Data\TrackingSessionCloseReason;
use App\Modules\Tracking\Data\TrackingSessionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Session de suivi d'un départ en circulation : liée au départ, au bus, au
 * terminal (P501 ou téléphone — voir TerminalType) et au convoyeur. Ouverte
 * au départ effectif, close à l'arrivée ou à l'annulation (voir
 * TrackingSessionObserver). Une seule session ouverte par départ à la fois.
 *
 * La dernière position est dénormalisée ici (last_*) pour que l'endpoint
 * fleet/tracking, lu très fréquemment, n'ait jamais besoin de joindre
 * tracking_positions — voir aussi TrackingPositionCache pour la couche
 * cache devant la base.
 *
 * @property int $id
 * @property string $uuid
 * @property TrackingSessionStatus $status
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property TrackingSessionCloseReason|null $close_reason
 * @property Carbon|null $last_position_at
 * @property float|null $last_latitude
 * @property float|null $last_longitude
 * @property float|null $last_accuracy_meters
 * @property float|null $last_speed_kmh
 * @property float|null $last_heading_degrees
 * @property TrackingPositionQuality|null $last_quality
 * @property TrackingPositionStatus|null $last_status
 * @property int|null $last_sequence
 * @property Departure $departure
 * @property Bus $bus
 * @property TerminalP501 $terminal
 * @property User $conductor
 */
class TrackingSession extends KoraModel
{
    protected $fillable = [
        'uuid',
        'departure_id',
        'bus_id',
        'terminal_p501_id',
        'conductor_id',
        'status',
        'opened_at',
        'closed_at',
        'close_reason',
        'last_position_id',
        'last_position_at',
        'last_latitude',
        'last_longitude',
        'last_accuracy_meters',
        'last_speed_kmh',
        'last_heading_degrees',
        'last_quality',
        'last_status',
        'last_sequence',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrackingSessionStatus::class,
            'close_reason' => TrackingSessionCloseReason::class,
            'last_quality' => TrackingPositionQuality::class,
            'last_status' => TrackingPositionStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_position_at' => 'datetime',
            'last_latitude' => 'float',
            'last_longitude' => 'float',
            'last_accuracy_meters' => 'float',
            'last_speed_kmh' => 'float',
            'last_heading_degrees' => 'float',
            'last_sequence' => 'integer',
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
     * @return BelongsTo<TerminalP501, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TerminalP501::class, 'terminal_p501_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conductor_id');
    }

    /**
     * @return HasMany<TrackingPosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(TrackingPosition::class);
    }

    /**
     * Dernière position ingérée (toutes statuts confondus, y compris les
     * heartbeats `gps_unavailable`) — donne notamment la `source` affichée.
     *
     * @return BelongsTo<TrackingPosition, $this>
     */
    public function lastPosition(): BelongsTo
    {
        return $this->belongsTo(TrackingPosition::class, 'last_position_id');
    }

    /**
     * @param  Builder<TrackingSession>  $query
     * @return Builder<TrackingSession>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', TrackingSessionStatus::Open);
    }

    public function isOpen(): bool
    {
        return $this->status === TrackingSessionStatus::Open;
    }
}
