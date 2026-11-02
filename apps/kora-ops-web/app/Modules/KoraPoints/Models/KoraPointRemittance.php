<?php

namespace App\Modules\KoraPoints\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $declared_by
 * @property int $amount
 * @property int|null $received_amount
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $validated_at
 */
class KoraPointRemittance extends KoraModel
{
    protected $attributes = ['status' => 'draft'];

    protected $table = 'kora_point_remittances';

    protected $fillable = ['organization_id', 'declared_by', 'validated_by', 'status', 'amount', 'received_amount', 'method', 'reference', 'note', 'submitted_at', 'validated_at', 'uuid'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'received_amount' => 'integer', 'submitted_at' => 'immutable_datetime', 'validated_at' => 'immutable_datetime'];
    }

    /** @return HasMany<KoraPointRemittanceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(KoraPointRemittanceLine::class, 'remittance_id');
    }

    /** @return BelongsTo<User, $this> */
    public function declarant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by');
    }

    /** Forbids deleting a remittance slip. */
    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Un bordereau de reversement ne peut pas être supprimé.'));
    }
}
