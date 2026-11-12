<?php

namespace App\Modules\FleetLite\Models;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Departures\Models\Departure;
use App\Modules\FleetLite\Data\OdometerReadingKind;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Relevé compteur (km) du carnet de bord, au départ ou à l'arrivée d'un
 * voyage. Unique par (départ, type) : une nouvelle saisie corrige la
 * précédente.
 *
 * @property int $departure_id
 * @property int|null $bus_id
 * @property OdometerReadingKind $kind
 * @property int $value_km
 * @property Carbon $recorded_at
 * @property int|null $recorded_by
 */
class DepartureOdometerReading extends KoraModel
{
    protected $fillable = [
        'uuid',
        'departure_id',
        'bus_id',
        'kind',
        'value_km',
        'recorded_at',
        'recorded_by',
        'terminal_p501_id',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'kind' => OdometerReadingKind::class,
            'value_km' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Departure, $this> */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class)->withTrashed();
    }

    /** @return BelongsTo<Bus, $this> */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<TerminalP501, $this> */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TerminalP501::class, 'terminal_p501_id');
    }
}
