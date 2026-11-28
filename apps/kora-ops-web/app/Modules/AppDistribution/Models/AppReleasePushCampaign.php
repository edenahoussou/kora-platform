<?php

namespace App\Modules\AppDistribution\Models;

use App\Models\User;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Data\ReleaseEnvironment;
use App\Modules\AppDistribution\Data\ReleasePlatform;
use App\Modules\AppDistribution\Data\ReleasePushStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Campagne FCM par topic pour une release store (#342).
 *
 * Une release ne peut porter qu'une seule campagne. Le topic est dérivé de
 * `app × platform × environment` ; `uncertain` ne peut être relancé qu'après
 * une décision Ops explicite enregistrée sur la campagne.
 *
 * @property int $id
 * @property string $uuid
 * @property int $release_id
 * @property ReleasedApp $app
 * @property ReleasePlatform $platform
 * @property ReleaseEnvironment $environment
 * @property string $topic
 * @property ReleasePushStatus $status
 * @property string $title
 * @property string $body
 * @property array<string, string>|null $data
 * @property string|null $fcm_message_id
 * @property string|null $error
 * @property int $attempts
 * @property int|null $created_by
 * @property int|null $retry_decision_by
 * @property Carbon|null $retry_decision_at
 * @property string|null $retry_decision_note
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property AppRelease|null $release
 * @property User|null $creator
 * @property User|null $retryDecisionBy
 */
class AppReleasePushCampaign extends KoraModel
{
    protected $table = 'app_release_push_campaigns';

    protected $fillable = [
        'uuid',
        'release_id',
        'app',
        'platform',
        'environment',
        'topic',
        'status',
        'title',
        'body',
        'data',
        'fcm_message_id',
        'error',
        'attempts',
        'created_by',
        'retry_decision_by',
        'retry_decision_at',
        'retry_decision_note',
        'accepted_at',
    ];

    protected $hidden = ['id', 'release_id', 'created_by', 'retry_decision_by'];

    protected function casts(): array
    {
        return [
            'app' => ReleasedApp::class,
            'platform' => ReleasePlatform::class,
            'environment' => ReleaseEnvironment::class,
            'status' => ReleasePushStatus::class,
            'data' => 'array',
            'attempts' => 'integer',
            'retry_decision_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AppRelease, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(AppRelease::class, 'release_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function retryDecisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retry_decision_by');
    }

    public function canRetry(): bool
    {
        return $this->status === ReleasePushStatus::Uncertain
            && $this->retry_decision_at !== null
            && $this->retry_decision_by !== null;
    }
}
