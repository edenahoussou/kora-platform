<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\BusNetwork\Data\CompositionStatus;
use App\Modules\BusNetwork\Data\SeatLayout;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property list<array{type?: string, codes: list<string>}> $layout
 * @property array{rows: int, cols: int, cells: list<array<string, mixed>>}|null $grid
 */
class BusComposition extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
        'seat_count',
        'layout',
        'grid',
        'status',
    ];

    /**
     * Grille canonique du gabarit — dérivée de `layout` pour les
     * compositions héritées qui n'ont pas encore de grille explicite.
     */
    public function seatGrid(): SeatLayout
    {
        if (is_array($this->grid) && ($this->grid['cells'] ?? []) !== []) {
            return SeatLayout::fromGridArray($this->grid);
        }

        return SeatLayout::fromLegacyLayout($this->layout ?? []);
    }

    /** Un gabarit affecté à au moins un bus ne se modifie plus : on duplique. */
    public function isLocked(): bool
    {
        return $this->buses()->exists();
    }

    /**
     * @return HasMany<Bus, $this>
     */
    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class, 'composition_id');
    }

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'grid' => 'array',
            'status' => CompositionStatus::class,
        ];
    }
}
