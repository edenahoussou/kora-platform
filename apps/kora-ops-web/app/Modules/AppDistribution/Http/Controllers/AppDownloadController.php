<?php

namespace App\Modules\AppDistribution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Models\AppRelease;
use App\Modules\AppDistribution\Services\AppReleaseCatalog;
use App\Modules\Ticketing\Actions\GenerateTicketQrCodeAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Distribution publique des applications hors store (MEZ-150).
 *
 * Volontairement sans authentification : les P501 n'ont pas de session
 * OPS Web et le lien est partagé aux équipes terrain (WhatsApp, e-mail).
 * Aucune liste de fichiers n'est exposée : seule la dernière release
 * publiée est atteignable sans connaître l'UUID d'une release précise.
 *
 * - `show`    : page HTML rendue côté serveur (meta SEO + Open Graph lisibles
 *               par les robots d'aperçu de lien, qui n'exécutent pas de JS) ;
 * - `latest`  : binaire de la dernière release publiée non archivée ;
 * - `release` : binaire d'une release précise (lien immuable, sha256 stable).
 */
class AppDownloadController extends Controller
{
    private const APK_MIME = 'application/vnd.android.package-archive';

    public function __construct(private AppReleaseCatalog $catalog) {}

    public function show(string $app, GenerateTicketQrCodeAction $qrCode): Response|RedirectResponse
    {
        $releasedApp = $this->resolveApp($app);

        // Une seule URL canonique par application (`ops-mobile`) : l'alias
        // `ops_mobile` redirige définitivement.
        if ($app !== $releasedApp->slug()) {
            return redirect()->route('downloads.apps.show', ['app' => $releasedApp->slug()], 301);
        }

        $latest = $this->catalog->latest($releasedApp);
        $pageUrl = route('downloads.apps.show', ['app' => $releasedApp->slug()]);

        return response()
            ->view('downloads.app', [
                'app' => $releasedApp,
                'release' => $latest,
                'pageUrl' => $pageUrl,
                'downloadUrl' => $latest ? route('downloads.apps.latest', ['app' => $releasedApp->slug()]) : null,
                'qrCode' => $qrCode->execute($pageUrl, 168),
                'title' => $latest
                    ? sprintf('Télécharger %s %s pour Android', $releasedApp->label(), $latest->version)
                    : sprintf('Télécharger %s pour Android', $releasedApp->label()),
                'description' => $releasedApp->description(),
                'imageUrl' => asset('android-chrome-512x512.png'),
            ])
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function latest(string $app): BinaryFileResponse
    {
        $releasedApp = $this->resolveApp($app);
        $latest = $this->catalog->latest($releasedApp);

        abort_if($latest === null, 404);

        return $this->binary($latest, 'no-cache');
    }

    public function release(string $app, string $release): BinaryFileResponse
    {
        $releasedApp = $this->resolveApp($app);

        $model = AppRelease::query()
            ->forApp($releasedApp)
            ->published()
            ->where('uuid', $release)
            ->first();

        abort_if($model === null, 404);

        // Contenu immuable pour une UUID donnée : cache long autorisé.
        return $this->binary($model, 'public, max-age=31536000, immutable');
    }

    private function resolveApp(string $app): ReleasedApp
    {
        $releasedApp = ReleasedApp::fromRouteKey($app);

        abort_if($releasedApp === null, 404);

        return $releasedApp;
    }

    /**
     * BinaryFileResponse gère nativement les requêtes `Range` : un
     * téléchargement interrompu sur le terrain (réseau mobile instable)
     * peut reprendre là où il s'est arrêté.
     */
    private function binary(AppRelease $release, string $cacheControl): BinaryFileResponse
    {
        $disk = Storage::disk(AppRelease::DISK);

        abort_unless($disk->exists($release->artifact_path), 404);

        return response()
            ->download($disk->path($release->artifact_path), $release->downloadFilename(), [
                'Content-Type' => self::APK_MIME,
                'Cache-Control' => $cacheControl,
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex',
                'X-Checksum-SHA256' => $release->sha256,
                'X-App-Version' => $release->version,
                'X-App-Build' => (string) $release->build_number,
            ], ResponseHeaderBag::DISPOSITION_ATTACHMENT)
            ->setEtag($release->sha256)
            ->setLastModified($release->published_at?->toDateTime());
    }
}
