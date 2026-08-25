<?php

namespace App\Modules\IdentityAccess\Models;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agency extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'city',
        'country',
        'head_user_id',
        'status',
    ];

    protected function casts(): array
    {
        return ['status' => AgencyStatus::class];
    }

    /**
     * @return HasMany<Bus, $this>
     */
    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class);
    }

    /**
     * @return HasMany<Departure, $this>
     */
    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /**
     * @return HasMany<AgencyPaymentAccount, $this>
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(AgencyPaymentAccount::class);
    }
}
