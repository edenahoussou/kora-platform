<?php

namespace App\Modules\KoraPoints\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $point_id
 * @property int $created_by
 * @property string $visit_type
 * @property int $score
 * @property string $report
 * @property string $status
 * @property Carbon|null $created_at
 */
class KoraPointVisit extends KoraModel
{
    protected $table = 'kora_point_visits';

    protected $fillable = ['point_id', 'created_by', 'visit_type', 'score', 'report', 'status'];

    protected function casts(): array
    {
        return ['score' => 'integer'];
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

    /** @return HasMany<KoraPointVisitAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(KoraPointVisitAction::class, 'visit_id');
    }

    /** @return HasMany<KoraPointVisitPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(KoraPointVisitPhoto::class, 'visit_id');
    }
}
