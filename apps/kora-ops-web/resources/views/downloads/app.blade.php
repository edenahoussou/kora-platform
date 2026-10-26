@php
    /** @var \App\Modules\AppDistribution\Data\ReleasedApp $app */
    /** @var \App\Modules\AppDistribution\Models\AppRelease|null $release */
    // Formatage local (sans dépendance à l'extension intl) : « 24,3 Mo ».
    $sizeLabel = match (true) {
        $release === null => null,
        $release->artifact_size >= 1_048_576 => number_format($release->artifact_size / 1_048_576, 1, ',', ' ').' Mo',
        default => max(1, (int) round($release->artifact_size / 1024)).' Ko',
    };
    $publishedLabel = $release?->published_at?->locale('fr')->translatedFormat('j F Y');
    $siteName = config('app.name', 'KORA OPS');
@endphp
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#001d4e">

    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $pageUrl }}">
    @if ($release)
        <meta name="robots" content="index, follow">
    @else
        <meta name="robots" content="noindex, follow">
    @endif

    {{-- Open Graph : aperçu du lien partagé sur WhatsApp, Facebook, e-mail… --}}
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $pageUrl }}">
    <meta property="og:image" content="{{ $imageUrl }}">
    <meta property="og:image:width" content="512">
    <meta property="og:image:height" content="512">
    <meta property="og:image:alt" content="Logo {{ $app->label() }}">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $imageUrl }}">

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

    @if ($release)
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => $app->label(),
                'operatingSystem' => 'Android',
                'applicationCategory' => 'BusinessApplication',
                'softwareVersion' => $release->version,
                'datePublished' => $release->published_at?->toDateString(),
                'description' => $description,
                'downloadUrl' => $downloadUrl,
                'fileSize' => $sizeLabel,
                'image' => $imageUrl,
                'url' => $pageUrl,
                'publisher' => ['@type' => 'Organization', 'name' => 'KORA EXPRESS'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
        </script>
    @endif

    <style>
        :root {
            --kora: #001d4e;
            --kora-deep: #00153b;
            --kora-soft: #00306e;
            --gold: #f6b000;
            --on-gold: #1a1400;
            --ink: #0f172a;
            --muted: #5b6474;
            --line: rgba(0, 29, 78, 0.1);
            --card: #ffffff;
            --mist: #f4f6f8;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: 'Inter', 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--ink);
            background: var(--mist);
            -webkit-font-smoothing: antialiased;
        }

        .hero {
            background: linear-gradient(155deg, var(--kora-deep) 0%, var(--kora) 64%, var(--kora-soft) 100%);
            color: #fff;
            padding: 40px 20px 120px;
            position: relative;
            overflow: hidden;
        }

        .hero::after {
            content: '';
            position: absolute;
            width: 420px;
            height: 420px;
            right: -140px;
            top: -160px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 0 60px rgba(255, 255, 255, 0.03);
        }

        .container {
            max-width: 720px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            font-size: 15px;
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: var(--gold);
            color: var(--kora);
            display: grid;
            place-items: center;
            font-weight: 900;
            font-size: 20px;
            box-shadow: 0 10px 26px rgba(246, 176, 0, 0.32);
        }

        .eyebrow {
            margin: 36px 0 10px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--gold);
        }

        h1 {
            margin: 0;
            font-size: clamp(28px, 6vw, 42px);
            line-height: 1.1;
            letter-spacing: -0.03em;
            font-weight: 800;
        }

        .lead {
            margin: 14px 0 0;
            max-width: 560px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 16px;
            line-height: 1.6;
        }

        .card {
            background: var(--card);
            border-radius: 22px;
            box-shadow: 0 24px 60px rgba(0, 29, 78, 0.14);
            padding: 28px;
            margin-top: -84px;
            position: relative;
            z-index: 2;
        }

        .release-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .version {
            font-size: 13px;
            color: var(--muted);
        }

        .version strong {
            display: block;
            font-size: 26px;
            color: var(--kora);
            letter-spacing: -0.02em;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--mist);
            color: var(--kora);
            font-size: 12px;
            font-weight: 700;
        }

        .chip-alert {
            background: rgba(156, 36, 48, 0.1);
            color: #9c2430;
        }

        .cta {
            margin-top: 24px;
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 18px 22px;
            border-radius: 16px;
            background: var(--gold);
            color: var(--on-gold);
            font-weight: 800;
            font-size: 17px;
            text-decoration: none;
            box-shadow: 0 14px 30px rgba(246, 176, 0, 0.32);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .cta:hover,
        .cta:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 18px 36px rgba(246, 176, 0, 0.4);
            outline: none;
        }

        .cta svg {
            width: 22px;
            height: 22px;
        }

        .cta-hint {
            margin: 10px 0 0;
            text-align: center;
            font-size: 12px;
            color: var(--muted);
        }

        .section {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--line);
        }

        .section h2 {
            margin: 0 0 12px;
            font-size: 15px;
            font-weight: 800;
            color: var(--kora);
        }

        .notes {
            margin: 0;
            white-space: pre-line;
            line-height: 1.65;
            font-size: 15px;
            color: #273142;
        }

        ol.steps {
            margin: 0;
            padding-left: 20px;
            line-height: 1.75;
            font-size: 14px;
            color: #273142;
        }

        .split {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            align-items: center;
        }

        .qr {
            justify-self: center;
            text-align: center;
            font-size: 12px;
            color: var(--muted);
        }

        .qr-frame {
            display: inline-block;
            padding: 10px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid var(--line);
        }

        .qr-frame svg {
            display: block;
            width: 148px;
            height: 148px;
        }

        details {
            margin-top: 18px;
            font-size: 12px;
            color: var(--muted);
        }

        details summary {
            cursor: pointer;
            font-weight: 700;
        }

        code {
            display: block;
            margin-top: 8px;
            padding: 10px 12px;
            border-radius: 10px;
            background: var(--mist);
            color: var(--kora);
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 11px;
            word-break: break-all;
        }

        .empty {
            text-align: center;
            padding: 12px 0;
        }

        .empty h2 {
            margin: 0 0 8px;
            color: var(--kora);
            font-size: 20px;
        }

        .empty p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        footer {
            text-align: center;
            padding: 32px 20px 40px;
            font-size: 12px;
            color: var(--muted);
        }

        @media (min-width: 640px) {
            .hero {
                padding: 56px 32px 132px;
            }

            .card {
                padding: 36px;
            }

            .split {
                grid-template-columns: 1fr auto;
            }
        }
    </style>
</head>

<body>
    <header class="hero">
        <div class="container">
            <span class="brand">
                <span class="brand-mark" aria-hidden="true">K</span>
                KORA OPS
            </span>

            <p class="eyebrow">Application Android · Distribution interne</p>
            <h1>{{ $app->label() }}</h1>
            <p class="lead">{{ $app->audience() }}</p>
        </div>
    </header>

    <main class="container" style="padding: 0 16px;">
        <section class="card" aria-labelledby="release-title">
            @if ($release)
                <div class="release-head">
                    <div class="version" id="release-title">
                        Dernière version
                        <strong>{{ $release->version }} <span style="font-size: 14px; color: var(--muted); font-weight: 600;">(build {{ $release->build_number }})</span></strong>
                    </div>
                    <div class="chips">
                        @if ($publishedLabel)
                            <span class="chip">Publiée le {{ $publishedLabel }}</span>
                        @endif
                        <span class="chip">{{ $sizeLabel }}</span>
                        @if ($release->is_mandatory)
                            <span class="chip chip-alert">Mise à jour obligatoire</span>
                        @endif
                    </div>
                </div>

                <a class="cta" href="{{ $downloadUrl }}" download="{{ $release->downloadFilename() }}" rel="nofollow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3v12" />
                        <path d="m7 10 5 5 5-5" />
                        <path d="M5 21h14" />
                    </svg>
                    Télécharger l’application
                </a>
                <p class="cta-hint">Fichier APK pour Android · {{ $release->downloadFilename() }}</p>

                @if ($release->release_notes)
                    <div class="section">
                        <h2>Nouveautés de cette version</h2>
                        <p class="notes">{{ $release->release_notes }}</p>
                    </div>
                @endif

                <div class="section split">
                    <div>
                        <h2>Installer en 3 étapes</h2>
                        <ol class="steps">
                            <li>Touchez « Télécharger l’application » depuis le terminal ou le téléphone.</li>
                            <li>Ouvrez le fichier téléchargé ; si Android le demande, autorisez l’installation depuis cette source.</li>
                            <li>Touchez « Installer » (ou « Mettre à jour »), puis ouvrez {{ $app->label() }}.</li>
                        </ol>
                    </div>
                    <div class="qr">
                        <div class="qr-frame" aria-hidden="true">{!! $qrCode !!}</div>
                        <p>Scannez pour ouvrir cette page<br>sur le terminal.</p>
                    </div>
                </div>

                <details>
                    <summary>Vérifier l’intégrité du fichier (SHA-256)</summary>
                    <code>{{ $release->sha256 }}</code>
                </details>
            @else
                <div class="empty" id="release-title">
                    <h2>Aucune version disponible pour le moment</h2>
                    <p>La première version de {{ $app->label() }} n’a pas encore été publiée.<br>Rapprochez-vous de votre agence ou de l’équipe KORA OPS.</p>
                </div>
            @endif
        </section>
    </main>

    <footer>
        © {{ now()->year }} KORA EXPRESS · Application réservée aux équipes KORA. Ne partagez ce lien qu’avec vos collègues.
    </footer>
</body>

</html>
