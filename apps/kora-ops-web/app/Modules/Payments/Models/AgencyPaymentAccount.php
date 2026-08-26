<?php

namespace App\Modules\Payments\Models;

use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $uuid
 * @property MobileMoneyNetwork $network
 * @property string $label
 * @property string $account_identifier
 */
class AgencyPaymentAccount extends KoraModel
{
    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (! $model->getAttribute('uuid')) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }

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
