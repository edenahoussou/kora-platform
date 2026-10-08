<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property PassengerDocumentType|null $document_type
 * @property Carbon|null $birth_date
 * @property string|null $email
 * @property string $status
 * @property int|null $passenger_index
 * @property bool|null $yellow_fever_vaccinated
 */
class ReservationPassenger extends KoraModel
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'reservation_id',
        'user_id',
        'status',
        'passenger_index',
        'refunded_amount',
        'first_name',
        'last_name',
        'phone',
        'email',
        'document_type',
        'document_number',
        'nationality',
        'birth_date',
        'yellow_fever_vaccinated',
        'anonymized_at',
    ];

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ReservationSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(ReservationSegment::class, 'reservation_passenger_id');
    }

    protected function casts(): array
    {
        return [
            'document_type' => PassengerDocumentType::class,
            'birth_date' => 'date',
            'refunded_amount' => 'integer',
            'passenger_index' => 'integer',
            'yellow_fever_vaccinated' => 'boolean',
            'anonymized_at' => 'datetime',
        ];
    }
}
