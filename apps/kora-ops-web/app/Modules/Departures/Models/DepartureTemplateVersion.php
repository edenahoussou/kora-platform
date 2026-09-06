<?php

namespace App\Modules\Departures\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<array<string, mixed>> $slots
 */
class DepartureTemplateVersion extends KoraModel
{
    protected $fillable = [
        'uuid',
        'template_id',
        'number',
        'slots',
    ];

    protected function casts(): array
    {
        return ['slots' => 'array', 'number' => 'integer'];
    }

    /** @return BelongsTo<DepartureTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DepartureTemplate::class, 'template_id');
    }
}
