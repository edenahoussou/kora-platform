<?php

namespace App\Modules\Passengers\Models;

use App\Models\User;
use App\Modules\Passengers\Data\PassengerErasureStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registre des demandes d'effacement de compte passager (droit à l'oubli).
 *
 * @property PassengerErasureStatus $status
 * @property array<string, mixed>|null $scope
 * @property Carbon|null $executed_at
 */
class PassengerErasureRequest extends KoraModel
{
    protected $fillable = [
        'uuid',
        'phone_hash',
        'requested_by_user_id',
        'actor_name',
        'requester_phone',
        'requester_email',
        'status',
        'scope',
        'export_checksum',
        'executed_at',
        'ip_address',
    ];

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => PassengerErasureStatus::class,
            'scope' => 'array',
            'executed_at' => 'datetime',
        ];
    }
}
