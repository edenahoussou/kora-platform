<?php

namespace App\Modules\DynamicPricing\Models;

use App\Models\User;
use App\Modules\DynamicPricing\Data\TaxRuleStatus;
use App\Modules\DynamicPricing\Data\TaxServiceType;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Taux fiscal paramétrable (CDC §9.8 : "Aucun taux fiscal ne doit être codé en
 * dur. Les groupes/taux applicables aux billets de transport, colis, frais et
 * autres services doivent être confirmés avec la DGI avant production, puis
 * stockés comme paramètres historisés."). Alimente à terme la génération des
 * factures MECeF (Fiscalization) — non câblé ici, hors périmètre de ce ticket.
 */
class TaxRule extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'service_type',
        'scope',
        'tax_code',
        'tax_rate',
        'starts_on',
        'ends_on',
        'status',
        'validated_by',
    ];

    protected function casts(): array
    {
        return [
            'service_type' => TaxServiceType::class,
            'tax_rate' => 'decimal:4',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => TaxRuleStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
