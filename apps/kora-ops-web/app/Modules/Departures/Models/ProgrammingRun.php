<?php

namespace App\Modules\Departures\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $result
 */
class ProgrammingRun extends KoraModel
{
    protected $fillable = ['uuid', 'programme_id', 'operation', 'status', 'processed', 'result', 'created_by'];

    protected function casts(): array
    {
        return ['result' => 'array', 'processed' => 'integer'];
    }

    /** @return BelongsTo<DepartureProgramme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(DepartureProgramme::class, 'programme_id');
    }
}
