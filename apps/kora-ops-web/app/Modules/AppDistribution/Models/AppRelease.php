<?php

namespace App\Modules\AppDistribution\Models;

use App\Models\User;
use App\Modules\AppDistribution\Data\AppVersion;
use App\Modules\AppDistribution\Data\ReleaseChannel;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Data\ReleaseEnvironment;
use App\Modules\AppDistribution\Data\ReleasePlatform;
use App\Modules\AppDistribution\Services\AppReleaseCatalog;
use App\Modules\AppDistribution\Services\ReleaseTopicResolver;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\AppReleaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Release d'une application distribuée par KORA OPS Web.
 *
 * Canal `ops_apk` : binaire APK stocké localement pour le Convoyeur.
 * Canal `store` : release Passager sur un store Android/iOS, sans binaire.
 *
 * @property int $id
 * @property string $uuid
 * @property ReleasedApp $app
 * @property ReleaseChannel $channel
 * @property ReleasePlatform|null $platform
 * @property string|null $store_url
 * @property ReleaseEnvironment|null $environment
 * @property string $version
 * @property int $build_number
 * @property string $artifact_path
 * @property string|null $original_filename
 * @property int $artifact_size
 * @property string $sha256
 * @property string|null $release_notes
 * @property bool $is_mandatory
 * @property Carbon|null $published_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $publicly_confirmed_at
 * @property Carbon|null $rollout_completed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User|null $creator
 * @property AppReleasePushCampaign|null $pushCampaign
 */
class AppRelease extends KoraModel
{
    /** @use HasFactory<AppReleaseFactory> */
    use HasFactory;

    /** Disque privé des binaires (storage/app/releases), voir config/filesystems.php. */
    public const DISK = 'releases';

    protected static function newFactory(): AppReleaseFactory
    {
        return AppReleaseFactory::new();
    }

    /**
     * Toute écriture (publication, archivage, confirmation store, campagne)
     * invalide immédiatement le calcul « dernière version » mis en cache.
     */
    protected static function booted(): void
    {
        $forget = static function (AppRelease $release): void {
            app(AppReleaseCatalog::class)->forget($release->app);
        };

        static::saved($forget);
        static::deleted($forget);
    }

    protected $table = 'app_releases';

    protected $fillable = [
        'uuid',
        'app',
        'channel',
        'platform',
        'store_url',
        'environment',
        'version',
        'build_number',
        'artifact_path',
        'original_filename',
        'artifact_size',
        'sha256',
        'release_notes',
        'is_mandatory',
        'published_at',
        'archived_at',
        'publicly_confirmed_at',
        'rollout_completed_at',
        'created_by',
    ];

    protected $hidden = ['id', 'artifact_path', 'created_by'];

    protected function casts(): array
    {
        return [
            'app' => ReleasedApp::class,
            'channel' => ReleaseChannel::class,
            'platform' => ReleasePlatform::class,
            'environment' => ReleaseEnvironment::class,
            'build_number' => 'integer',
            'artifact_size' => 'integer',
            'is_mandatory' => 'boolean',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'publicly_confirmed_at' => 'datetime',
            'rollout_completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasOne<AppReleasePushCampaign, $this>
     */
    public function pushCampaign(): HasOne
    {
        return $this->hasOne(AppReleasePushCampaign::class, 'release_id');
    }

    /**
     * Releases candidates au calcul « dernière version » : publiées (date
     * de publication atteinte) et non archivées.
     *
     * @param  Builder<AppRelease>  $query
     * @return Builder<AppRelease>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNull('archived_at')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<AppRelease>  $query
     * @return Builder<AppRelease>
     */
    public function scopeForApp(Builder $query, ReleasedApp $app): Builder
    {
        return $query->where('app', $app->value);
    }

    public function appVersion(): AppVersion
    {
        return AppVersion::fromParts($this->version, $this->build_number)
            ?? new AppVersion(0, 0, 0, null, $this->build_number);
    }

    public function isStore(): bool
    {
        return $this->channel === ReleaseChannel::Store;
    }

    public function isStorePubliclyVisible(): bool
    {
        return $this->isStore()
            && $this->publicly_confirmed_at !== null
            && $this->rollout_completed_at !== null;
    }

    public function topicName(): string
    {
        return app(ReleaseTopicResolver::class)->resolve($this->platform, $this->environment);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isPublished(): bool
    {
        return ! $this->isArchived()
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    /**
     * Nom de fichier proposé au navigateur / à l'appareil.
     */
    public function downloadFilename(): string
    {
        $this->requireApk();

        return sprintf('%s-%s-%d.apk', $this->app->fileBasename(), $this->version, $this->build_number);
    }

    /**
     * URL publique et immuable du binaire de CETTE release.
     */
    public function downloadUrl(): string
    {
        $this->requireApk();

        return route('downloads.apps.release', [
            'app' => $this->app->slug(),
            'release' => $this->uuid,
        ]);
    }

    private function requireApk(): void
    {
        if ($this->isStore()) {
            throw new LogicException('A store release has no downloadable APK artifact.');
        }
    }
}
