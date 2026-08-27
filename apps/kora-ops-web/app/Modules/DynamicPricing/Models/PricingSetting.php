<?php

namespace App\Modules\DynamicPricing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Réglages globaux du moteur de tarification dynamique. Table à ligne unique :
 * utiliser PricingSetting::current() plutôt que Model::query()->first() partout,
 * pour centraliser la logique "aucun réglage = pas de bornage".
 */
class PricingSetting extends Model
{
    protected $fillable = [
        'floor_coefficient',
        'ceiling_coefficient',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'floor_coefficient' => 'decimal:4',
            'ceiling_coefficient' => 'decimal:4',
        ];
    }

    /**
     * Renvoie l'unique ligne de réglages, ou null si la Direction Commerciale
     * n'a encore rien configuré (dans ce cas : aucun plancher/plafond appliqué).
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }
}
