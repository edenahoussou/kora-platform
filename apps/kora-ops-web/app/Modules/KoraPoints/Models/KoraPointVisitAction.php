<?php

namespace App\Modules\KoraPoints\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $visit_id
 * @property int|null $assigned_user_id
 * @property int|null $reviewed_by
 * @property string $label
 * @property Carbon $due_date
 * @property string $status
 * @property string|null $realization_note
 * @property string|null $review_reason
 * @property Carbon|null $realized_at
 * @property Carbon|null $reviewed_at
 */
class KoraPointVisitAction extends KoraModel
{
    protected $table = 'kora_point_visit_actions';

    protected $fillable = ['visit_id', 'assigned_user_id', 'reviewed_by', 'label', 'due_date', 'status', 'realization_note', 'review_reason', 'realized_at', 'reviewed_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'realized_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<KoraPointVisit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(KoraPointVisit::class, 'visit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
