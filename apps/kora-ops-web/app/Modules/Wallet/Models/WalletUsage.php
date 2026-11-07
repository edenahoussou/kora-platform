<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une utilisation = un billet, comptée à sa première jambe effectuée.
 *
 * @property int $wallet_id
 * @property int $reservation_id
 * @property Carbon $used_at
 */
class WalletUsage extends KoraModel
{
    protected $fillable = [
        'uuid',
        'wallet_id',
        'reservation_id',
        'used_at',
    ];

    /**
     * Wallet crédité de l'utilisation.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Billet utilisé.
     *
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * Cast de l'horodatage d'utilisation.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }
}
