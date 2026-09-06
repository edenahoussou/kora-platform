<?php

namespace App\Modules\Booking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupBookingSetting extends Model
{
    protected $table = 'group_booking_settings';

    protected $fillable = [
        'minimum_passengers',
        'maximum_passengers',
        'updated_by',
    ];

    protected function casts(): array
    {
        return ['minimum_passengers' => 'integer', 'maximum_passengers' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'minimum_passengers' => 5,
            'maximum_passengers' => 10,
        ]);
    }
}
