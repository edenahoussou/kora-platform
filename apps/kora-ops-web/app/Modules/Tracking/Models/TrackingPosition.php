<?php

namespace App\Modules\Tracking\Models;

use App\Modules\Support\Models\KoraModel;
use App\Modules\Tracking\Data\TrackingPositionQuality;
use App\Modules\Tracking\Data\TrackingPositionSource;
use App\Modules\Tracking\Data\TrackingPositionStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une position brute reçue d'un terminal, historisée telle quelle — y
 * compris les positions dégradées (précision faible) et les heartbeats
 * `gps_unavailable` (sans coordonnées). L'idempotence du lot d'ingestion
 * repose sur `uuid`, fourni par le terminal (voir IngestTrackingPositionsBatchAction).
 *
 * @property int $id
 * @property string $uuid
 * @property int $sequence
 * @property TrackingPositionStatus $status
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float|null $accuracy_meters
 * @property float|null $speed_kmh
 * @property float|null $heading_degrees
 * @property TrackingPositionSource $source
 * @property TrackingPositionQuality $quality
 * @property Carbon $recorded_at
 * @property Carbon $server_received_at
 * @property TrackingSession $session
 */
class TrackingPosition extends KoraModel
{
    protected $table = 'tracking_positions';

    protected $fillable = [
        'uuid',
        'tracking_session_id',
        'sequence',
        'status',
        'latitude',
        'longitude',
        'accuracy_meters',
        'speed_kmh',
        'heading_degrees',
        'source',
        'quality',
        'recorded_at',
        'server_received_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrackingPositionStatus::class,
            'source' => TrackingPositionSource::class,
            'quality' => TrackingPositionQuality::class,
            'sequence' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meters' => 'float',
            'speed_kmh' => 'float',
            'heading_degrees' => 'float',
            'recorded_at' => 'datetime',
            'server_received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TrackingSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class, 'tracking_session_id');
    }
}
