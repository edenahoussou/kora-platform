<?php

namespace App\Modules\Parcels\Models;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelHub extends KoraModel
{
    protected $fillable = ['agency_id', 'stop_id', 'enabled'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<Stop, $this> */
    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class);
    }
}
