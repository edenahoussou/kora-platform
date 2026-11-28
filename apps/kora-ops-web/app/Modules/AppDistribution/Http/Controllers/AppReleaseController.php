<?php

namespace App\Modules\AppDistribution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AppDistribution\Actions\ArchiveAppReleaseAction;
use App\Modules\AppDistribution\Actions\ConfirmStoreReleaseAction;
use App\Modules\AppDistribution\Actions\CreateStoreAppReleaseAction;
use App\Modules\AppDistribution\Actions\DecideRetryPushCampaignAction;
use App\Modules\AppDistribution\Actions\DispatchReleasePushCampaignAction;
use App\Modules\AppDistribution\Actions\PublishAppReleaseAction;
use App\Modules\AppDistribution\Actions\RestoreAppReleaseAction;
use App\Modules\AppDistribution\Actions\UpdateAppReleaseAction;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Http\Requests\ConfirmStoreReleaseRequest;
use App\Modules\AppDistribution\Http\Requests\CreateStoreAppReleaseRequest;
use App\Modules\AppDistribution\Http\Requests\DispatchPushCampaignRequest;
use App\Modules\AppDistribution\Http\Requests\RetryPushCampaignRequest;
use App\Modules\AppDistribution\Http\Requests\StoreAppReleaseRequest;
use App\Modules\AppDistribution\Http\Requests\UpdateAppReleaseRequest;
use App\Modules\AppDistribution\Jobs\SendReleasePushCampaignJob;
use App\Modules\AppDistribution\Models\AppRelease;
use App\Modules\AppDistribution\Models\AppReleasePushCampaign;
use App\Modules\AppDistribution\Services\AppReleaseAdminReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration → Versions de l'application (#342) : publication des
 * packages APK Convoyeur, releases store Passager et campagnes push FCM.
 */
class AppReleaseController extends Controller
{
    public function index(Request $request, AppReleaseAdminReadService $read): Response
    {
        Gate::authorize('viewAny', AppRelease::class);

        $app = ReleasedApp::fromRouteKey($request->string('app')->value()) ?? ReleasedApp::OpsMobile;

        return Inertia::render('administration/releases', [
            'apps' => $read->apps(),
            'selectedApp' => $app->value,
            ...$read->overview($app),
            'constraints' => [
                'max_package_kilobytes' => StoreAppReleaseRequest::MAX_PACKAGE_KILOBYTES,
                'max_build_number' => StoreAppReleaseRequest::MAX_BUILD_NUMBER,
                'server_max_upload_bytes' => self::serverUploadLimitBytes(),
            ],
            'permissions' => [
                'canManage' => Gate::allows('create', AppRelease::class),
            ],
        ]);
    }

    /**
     * Plus petite des limites PHP d'upload (`upload_max_filesize`,
     * `post_max_size`), en octets — `null` si aucune limite n'est fixée.
     */
    private static function serverUploadLimitBytes(): ?int
    {
        $limits = array_filter(
            [
                self::iniBytes((string) ini_get('upload_max_filesize')),
                ($post = self::iniBytes((string) ini_get('post_max_size'))) === null ? null : $post - 65_536,
            ],
            static fn (?int $bytes): bool => $bytes !== null && $bytes > 0,
        );

        return $limits === [] ? null : min($limits);
    }

    /**
     * `64M` → 67108864 ; `0`/vide → null (pas de limite).
     */
    private static function iniBytes(string $value): ?int
    {
        $value = trim($value);

        if ($value === '' || $value === '0' || $value === '-1') {
            return null;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public function store(StoreAppReleaseRequest $request, PublishAppReleaseAction $publish): RedirectResponse
    {
        $app = $request->releasedApp();
        $release = $publish->execute($app, $request->package(), $request->payload(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Version :version (build :build) publiée.', [
                'version' => $release->version,
                'build' => $release->build_number,
            ]),
        ]);

        return to_route('administration.releases', ['app' => $app->value]);
    }

    public function storeStore(CreateStoreAppReleaseRequest $request, CreateStoreAppReleaseAction $action): RedirectResponse
    {
        $release = $action->execute($request->payload(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Release store :version (build :build) enregistrée.', [
                'version' => $release->version,
                'build' => $release->build_number,
            ]),
        ]);

        return to_route('administration.releases', ['app' => $release->app->value]);
    }

    public function confirmStore(ConfirmStoreReleaseRequest $request, AppRelease $release, ConfirmStoreReleaseAction $action): RedirectResponse
    {
        $action->execute($release, $request->rolloutCompleted(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Release store :version confirmée publiquement.', ['version' => $release->version])]);

        return to_route('administration.releases', ['app' => $release->app->value]);
    }

    public function dispatchCampaign(DispatchPushCampaignRequest $request, AppRelease $release, DispatchReleasePushCampaignAction $action): RedirectResponse
    {
        $campaign = $action->execute($release, $request->user(), $request->message());

        SendReleasePushCampaignJob::dispatch($campaign->id)->afterCommit();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Campagne push programmée pour la release :version.', ['version' => $release->version]),
        ]);

        return to_route('administration.releases', ['app' => $release->app->value]);
    }

    public function retryCampaign(RetryPushCampaignRequest $request, AppReleasePushCampaign $campaign, DecideRetryPushCampaignAction $action): RedirectResponse
    {
        $action->execute($campaign, $request->note(), $request->user());

        SendReleasePushCampaignJob::dispatch($campaign->id)->afterCommit();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Relance de la campagne push enregistrée.'),
        ]);

        return to_route('administration.releases', ['app' => $campaign->app->value]);
    }

    public function update(UpdateAppReleaseRequest $request, AppRelease $release, UpdateAppReleaseAction $updateRelease): RedirectResponse
    {
        $updateRelease->execute($release, $request->payload(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version :version mise à jour.', ['version' => $release->version])]);

        return to_route('administration.releases', ['app' => $release->app->value]);
    }

    public function archive(Request $request, AppRelease $release, ArchiveAppReleaseAction $archive): RedirectResponse
    {
        Gate::authorize('archive', $release);

        $archive->execute($release, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version :version archivée : elle n’est plus proposée au téléchargement.', ['version' => $release->version])]);

        return to_route('administration.releases', ['app' => $release->app->value]);
    }

    public function restore(Request $request, AppRelease $release, RestoreAppReleaseAction $restore): RedirectResponse
    {
        Gate::authorize('restore', $release);

        $restore->execute($release, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version :version republiée.', ['version' => $release->version])]);

        return to_route('administration.releases', ['app' => $release->app->value]);
    }
}
