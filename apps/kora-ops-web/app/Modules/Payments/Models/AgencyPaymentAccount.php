<?php

namespace App\Modules\Payments\Models;

use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $uuid
 * @property MobileMoneyNetwork $network
 * @property string $label
 * @property string $account_identifier
 */
class AgencyPaymentAccount extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'agency_id',
        'network',
        'label',
        'account_identifier',
        'status',
    ];

    protected function casts(): array
    {
        return ['network' => MobileMoneyNetwork::class];
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function isActive(): bool
    {
        return $this->getRawOriginal('status') === 'active';
    }
}
