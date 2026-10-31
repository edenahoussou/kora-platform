<?php

namespace App\Modules\KoraPoints\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $amount
 */
class KoraPointRemittanceLine extends KoraModel
{
    protected $table = 'kora_point_remittance_lines';

    protected $fillable = ['remittance_id', 'sale_id', 'claimed_sale_id', 'amount', 'uuid'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<KoraPointSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(KoraPointSale::class, 'sale_id');
    }

    /** @return BelongsTo<KoraPointRemittance, $this> */
    public function remittance(): BelongsTo
    {
        return $this->belongsTo(KoraPointRemittance::class, 'remittance_id');
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Une ligne de reversement ne peut pas être supprimée.'));
    }
}
