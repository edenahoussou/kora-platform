<?php

namespace App\Modules\AppDistribution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AppDistribution\Actions\ArchiveAppReleaseAction;
use App\Modules\AppDistribution\Actions\PublishAppReleaseAction;
use App\Modules\AppDistribution\Actions\RestoreAppReleaseAction;
use App\Modules\AppDistribution\Actions\UpdateAppReleaseAction;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Http\Requests\StoreAppReleaseRequest;
use App\Modules\AppDistribution\Http\Requests\UpdateAppReleaseRequest;
use App\Modules\AppDistribution\Models\AppRelease;
use App\Modules\AppDistribution\Services\AppReleaseAdminReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration → Versions de l'application (MEZ-150) : publication des
 * packages, politique de mise à jour et vue de la flotte.
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
            ],
            'permissions' => [
                'canManage' => Gate::allows('create', AppRelease::class),
            ],
        ]);
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
