<?php

use App\Modules\AppDistribution\Models\AppRelease;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake(AppRelease::DISK);
});

function storedRelease(string $version, int $build, string $content, array $attributes = []): AppRelease
{
    $release = AppRelease::factory()->version($version, $build)->create([
        'sha256' => hash('sha256', $content),
        'artifact_size' => strlen($content),
        ...$attributes,
    ]);

    Storage::disk(AppRelease::DISK)->put($release->artifact_path, $content);

    return $release;
}

it('renders a shareable download page with SEO and Open Graph meta tags', function (): void {
    storedRelease('1.4.0', 42, 'apk-binary', ['release_notes' => 'Correctifs d’impression du manifeste.']);

    $pageUrl = route('downloads.apps.show', ['app' => 'ops-mobile']);

    $this->get('/downloads/apps/ops-mobile')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=utf-8')
        ->assertSee('<title>Télécharger KORA OPS Mobile 1.4.0 pour Android</title>', false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('<link rel="canonical" href="'.$pageUrl.'">', false)
        ->assertSee('<meta property="og:title" content="Télécharger KORA OPS Mobile 1.4.0 pour Android">', false)
        ->assertSee('<meta property="og:description"', false)
        ->assertSee('<meta property="og:image" content="'.asset('android-chrome-512x512.png').'">', false)
        ->assertSee('<meta property="og:url" content="'.$pageUrl.'">', false)
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('Télécharger l’application')
        ->assertSee(route('downloads.apps.latest', ['app' => 'ops-mobile']), false)
        ->assertSee('Correctifs d’impression du manifeste.')
        ->assertSee('<svg', false);
});

it('redirects the underscore alias to the canonical download page', function (): void {
    $this->get('/downloads/apps/ops_mobile')
        ->assertMovedPermanently()
        ->assertRedirect(route('downloads.apps.show', ['app' => 'ops-mobile']));
});

it('keeps the download page reachable but not indexable while nothing is published', function (): void {
    $this->get('/downloads/apps/ops-mobile')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('Aucune version disponible pour le moment');
});

it('answers 404 for an unknown application', function (): void {
    $this->get('/downloads/apps/passenger-legacy')->assertNotFound();
    $this->get('/downloads/apps/passenger-legacy/latest.apk')->assertNotFound();
});

it('serves the latest published, non archived release as an apk download', function (): void {
    storedRelease('1.3.0', 35, 'old-binary');
    $latest = storedRelease('1.4.0', 42, 'latest-binary');
    storedRelease('1.5.0', 50, 'archived-binary', ['archived_at' => now()]);

    $response = $this->get('/downloads/apps/ops-mobile/latest.apk');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.android.package-archive')
        ->assertHeader('X-Checksum-SHA256', $latest->sha256)
        ->assertHeader('X-App-Version', '1.4.0')
        ->assertDownload('kora-ops-mobile-1.4.0-42.apk');

    expect($response->baseResponse->getFile()->getContent())->toBe('latest-binary');
});

it('answers 404 on latest.apk while no release is published', function (): void {
    storedRelease('1.4.0', 42, 'archived', ['archived_at' => now()]);

    $this->get('/downloads/apps/ops-mobile/latest.apk')->assertNotFound();
});

it('serves a specific release by uuid and hides archived ones', function (): void {
    $release = storedRelease('1.3.0', 35, 'specific-binary');
    $archived = storedRelease('1.2.0', 30, 'archived-binary', ['archived_at' => now()]);

    $response = $this->get(route('downloads.apps.release', ['app' => 'ops-mobile', 'release' => $release->uuid]));

    $response->assertOk()
        ->assertDownload('kora-ops-mobile-1.3.0-35.apk')
        ->assertHeader('X-Checksum-SHA256', $release->sha256);

    expect($response->baseResponse->getFile()->getContent())->toBe('specific-binary');

    $this->get(route('downloads.apps.release', ['app' => 'ops-mobile', 'release' => $archived->uuid]))
        ->assertNotFound();

    $this->get('/downloads/apps/ops-mobile/releases/'.Str::uuid())
        ->assertNotFound();
});

it('exposes no listing of the stored packages', function (): void {
    storedRelease('1.4.0', 42, 'binary');

    $this->get('/downloads/apps/ops-mobile/releases')->assertNotFound();
    $this->get('/downloads/apps')->assertNotFound();
    // Le disque `releases` n'est jamais servi directement (`serve => false`).
    expect($this->get('/storage/ops_mobile/'.AppRelease::query()->value('uuid').'.apk')->status())->toBeIn([403, 404]);
});
