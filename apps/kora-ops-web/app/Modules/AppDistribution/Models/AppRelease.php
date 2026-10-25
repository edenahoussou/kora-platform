<?php

namespace App\Modules\AppDistribution\Models;

use App\Models\User;
use App\Modules\AppDistribution\Data\AppVersion;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Services\AppReleaseCatalog;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\AppReleaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Package publié d'une application distribuée hors store (MEZ-150).
 *
 * @property int $id
 * @property string $uuid
 * @property ReleasedApp $app
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
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User|null $creator
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
     * Toute écriture (publication, archivage, changement du flag
     * obligatoire) invalide immédiatement le calcul « dernière version »
     * mis en cache par AppReleaseCatalog.
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
        'created_by',
    ];

    protected $hidden = ['id', 'artifact_path', 'created_by'];

    protected function casts(): array
    {
        return [
            'app' => ReleasedApp::class,
            'build_number' => 'integer',
            'artifact_size' => 'integer',
            'is_mandatory' => 'boolean',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
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
        return sprintf('%s-%s-%d.apk', $this->app->fileBasename(), $this->version, $this->build_number);
    }

    /**
     * URL publique et immuable du binaire de CETTE release — c'est elle
     * (et non `latest.apk`) qui est renvoyée aux appareils dans le bloc
     * `update`, pour que le sha256 annoncé corresponde toujours au fichier
     * téléchargé même si une release plus récente est publiée entre-temps.
     */
    public function downloadUrl(): string
    {
        return route('downloads.apps.release', [
            'app' => $this->app->slug(),
            'release' => $this->uuid,
        ]);
    }
}
