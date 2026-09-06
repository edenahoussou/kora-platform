<?php

namespace App\Modules\Departures\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $draft
 */
class DepartureTemplate extends KoraModel
{
    protected $fillable = [
        'uuid',
        'name',
        'status',
        'revision',
        'draft',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['draft' => 'array', 'revision' => 'integer'];
    }

    /** @return HasMany<DepartureTemplateVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DepartureTemplateVersion::class, 'template_id');
    }
}
