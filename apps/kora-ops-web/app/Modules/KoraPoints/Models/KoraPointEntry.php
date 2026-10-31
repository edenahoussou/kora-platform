<?php

namespace App\Modules\KoraPoints\Models;

use App\Modules\Support\Models\KoraModel;

class KoraPointEntry extends KoraModel
{
    protected $attributes = ['commission_delta' => 0];

    protected $table = 'kora_point_entries';

    protected $fillable = ['point_id', 'sale_id', 'actor_id', 'event_key', 'type', 'amount', 'commission_delta', 'uuid'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'commission_delta' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Le journal partenaire est immuable.'));
        static::deleting(fn () => throw new \LogicException('Le journal partenaire est immuable.'));
    }
}
