<?php

namespace App\Modules\KoraPoints\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $point_id
 * @property int $created_by
 * @property int|null $cancelled_by
 * @property string $support
 * @property int $quantity
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $installed_at
 * @property string|null $cancellation_reason
 * @property Carbon|null $cancelled_at
 */
class KoraPointPlvOrder extends KoraModel
{
    protected $table = 'kora_point_plv_orders';

    protected $fillable = ['point_id', 'created_by', 'cancelled_by', 'support', 'quantity', 'status', 'delivered_at', 'installed_at', 'cancellation_reason', 'cancelled_at'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'delivered_at' => 'datetime', 'installed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return BelongsTo<KoraPoint, $this> */
    public function point(): BelongsTo
    {
        return $this->belongsTo(KoraPoint::class, 'point_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
