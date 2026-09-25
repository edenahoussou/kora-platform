<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>{{ $document['number'] }} — Manifeste passagers</title>
    <style>
        @php $font =static fn (string $file): string=>'data:font/woff2;base64,' .base64_encode(file_get_contents(resource_path("fonts/geist/{$file}")));
        @endphp

        @font-face {
            font-family: 'Geist Sans';
            font-weight: 400;
            src: url('{{ $font('geist-sans-latin-400-normal.woff2') }}') format('woff2');
        }

        @font-face {
            font-family: 'Geist Sans';
            font-weight: 600;
            src: url('{{ $font('geist-sans-latin-600-normal.woff2') }}') format('woff2');
        }

        @font-face {
            font-family: 'Geist Sans';
            font-weight: 700;
            src: url('{{ $font('geist-sans-latin-700-normal.woff2') }}') format('woff2');
        }

        @font-face {
            font-family: 'Geist Sans';
            font-weight: 800;
            src: url('{{ $font('geist-sans-latin-800-normal.woff2') }}') format('woff2');
        }

        @font-face {
            font-family: 'Geist Mono';
            font-weight: 500;
            src: url('{{ $font('geist-mono-latin-500-normal.woff2') }}') format('woff2');
        }

        @page {
            size: A4 landscape;
            margin: 0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Geist Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #0f1b2d;
            -webkit-font-smoothing: antialiased;
        }

        /* ——— Bandeau société ——— */
        .letterhead {
            background: #001d4e;
            color: #ffffff;
            padding: 8mm 10mm 7mm;
            display: table;
            width: 100%;
        }

        .letterhead-brand {
            display: table-cell;
            vertical-align: middle;
            width: 60%;
        }

        .letterhead-brand img {
            height: 13mm;
            display: block;
        }

        .letterhead-company {
            margin-top: 3.5mm;
            font-size: 7pt;
            line-height: 1.65;
            color: rgba(255, 255, 255, 0.62);
        }

        .letterhead-doc {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 40%;
        }

        .doc-type {
            font-size: 15pt;
            font-weight: 800;
            letter-spacing: 0.5mm;
            color: #ffffff;
        }

        .doc-ref {
            margin-top: 1.5mm;
            font-family: 'Geist Mono', monospace;
            font-size: 8.5pt;
            color: #f6b000;
            letter-spacing: 0.3mm;
        }

        .doc-meta {
            margin-top: 2mm;
            font-size: 7pt;
            color: rgba(255, 255, 255, 0.55);
        }

        .gold-rule {
            height: 1.6mm;
            background: #f6b000;
        }

        /* ——— Corps ——— */
        .sheet {
            padding: 7mm 10mm 0;
        }

        .doc-title {
            display: table;
            width: 100%;
            margin-bottom: 5mm;
        }

        .doc-title-main {
            display: table-cell;
            vertical-align: bottom;
        }

        .doc-title h1 {
            font-size: 16pt;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #001d4e;
        }

        .doc-title p {
            margin-top: 1mm;
            font-size: 8pt;
            color: #5b6b8c;
        }

        .doc-status {
            display: table-cell;
            vertical-align: bottom;
            text-align: right;
        }

        .pill {
            display: inline-block;
            padding: 1.4mm 3mm;
            border-radius: 99mm;
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.25mm;
            text-transform: uppercase;
        }

        .pill-navy {
            background: #001d4e;
            color: #ffffff;
        }

        .pill-gold {
            background: #fdf3d7;
            color: #8a5f00;
        }

        .pill-green {
            background: #e4f5ec;
            color: #157a4c;
        }

        .pill-red {
            background: #fdeaea;
            color: #b42323;
        }

        .pill-muted {
            background: #eef1f7;
            color: #5b6b8c;
        }

        /* ——— Carte départ ——— */
        .departure-card {
            border: 0.25mm solid #d8dee9;
            border-radius: 2.5mm;
            overflow: hidden;
            margin-bottom: 5mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .departure-head {
            background: #f4f6f8;
            padding: 3mm 4.5mm;
            border-bottom: 0.25mm solid #d8dee9;
            display: table;
            width: 100%;
        }

        .departure-kicker {
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: 0.35mm;
            text-transform: uppercase;
            color: #0057b8;
        }

        .departure-route {
            font-size: 13pt;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #001d4e;
            margin-top: 0.8mm;
        }

        .departure-route .arrow {
            color: #f6b000;
            font-weight: 700;
            padding: 0 1mm;
        }

        .departure-sub {
            font-size: 7.5pt;
            color: #5b6b8c;
            margin-top: 0.8mm;
        }

        .departure-badge {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
        }

        .departure-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .departure-grid .row {
            display: table-row;
        }

        .departure-grid .cell {
            display: table-cell;
            width: 25%;
            padding: 2.6mm 4.5mm;
            border-right: 0.25mm solid #edf0f5;
            border-bottom: 0.25mm solid #edf0f5;
        }

        .departure-grid .cell:last-child {
            border-right: none;
        }

        .departure-grid .row:last-child .cell {
            border-bottom: none;
        }

        .cell-label {
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: 0.3mm;
            text-transform: uppercase;
            color: #8a97ad;
        }

        .cell-value {
            font-size: 9.5pt;
            font-weight: 700;
            color: #0f1b2d;
            margin-top: 0.8mm;
        }

        .cell-value small {
            font-weight: 400;
            color: #5b6b8c;
            font-size: 7.5pt;
        }

        /* ——— Compteurs ——— */
        .kpi-strip {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .kpi {
            display: table-cell;
            width: 25%;
            padding: 3mm 4mm;
            border-top: 0.9mm solid #f6b000;
            background: #ffffff;
            border-right: 0.25mm solid #edf0f5;
            border-bottom: 0.25mm solid #edf0f5;
        }

        .kpi:first-child {
            border-left: 0.25mm solid #edf0f5;
            border-radius: 2mm 0 0 2mm;
        }

        .kpi:last-child {
            border-radius: 0 2mm 2mm 0;
        }

        .kpi-value {
            font-size: 15pt;
            font-weight: 800;
            color: #001d4e;
            letter-spacing: -0.02em;
        }

        .kpi-value em {
            font-style: normal;
            font-size: 8pt;
            font-weight: 600;
            color: #8a97ad;
        }

        .kpi-label {
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: 0.3mm;
            text-transform: uppercase;
            color: #5b6b8c;
            margin-top: 0.6mm;
        }

        .kpi.kpi-green .kpi-value {
            color: #157a4c;
        }

        .kpi.kpi-red .kpi-value {
            color: #b42323;
        }

        .kpi.kpi-gold .kpi-value {
            color: #8a5f00;
        }

        /* ——— Table passagers — branding des tables Caisse/Paramétrage :
           carte bordée, en-tête de panneau avec titre, thead mist uppercase,
           lignes blanches séparées par des filets fins (pas de navy, pas de
           zébrure — lecture identique aux écrans de l'application). */
        .table-card {
            border: 0.25mm solid #d8dee9;
            border-radius: 2.5mm;
            overflow: hidden;
            background: #ffffff;
        }

        .table-card-head {
            display: table;
            width: 100%;
            padding: 2.8mm 4.5mm;
            border-bottom: 0.25mm solid #e4e9f1;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .table-card-head .tc-main {
            display: table-cell;
            vertical-align: middle;
        }

        .tc-title {
            font-size: 9pt;
            font-weight: 700;
            color: #0f1b2d;
        }

        .tc-sub {
            font-size: 6.8pt;
            color: #8a97ad;
            margin-top: 0.5mm;
        }

        .table-card-head .tc-side {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-family: 'Geist Mono', monospace;
            font-size: 7pt;
            color: #8a97ad;
        }

        .manifest-table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Pagination : l'en-tête se répète sur chaque page, aucune ligne
           n'est coupée en deux, et au moins 3 lignes restent groupées. */
        .manifest-table thead {
            display: table-header-group;
        }

        .manifest-table td,
        .manifest-table th {
            orphans: 3;
            widows: 3;
        }

        .manifest-table th {
            background: #f4f6f8;
            color: #5b6b8c;
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: 0.35mm;
            text-transform: uppercase;
            text-align: left;
            padding: 2.4mm 3mm;
            border-bottom: 0.25mm solid #e4e9f1;
        }

        .manifest-table td {
            padding: 2.4mm 3mm;
            border-bottom: 0.2mm solid #edf0f5;
            font-size: 8.5pt;
            vertical-align: middle;
        }

        .manifest-table tbody tr:last-child td {
            border-bottom: none;
        }

        .manifest-table tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .col-num {
            width: 7mm;
            color: #8a97ad;
            font-size: 7.5pt;
        }

        .col-seat {
            width: 12mm;
        }

        .seat-code {
            font-family: 'Geist Mono', monospace;
            font-weight: 500;
            font-size: 9pt;
            color: #001d4e;
            background: #eef1f7;
            border-radius: 1.2mm;
            padding: 0.8mm 1.6mm;
            display: inline-block;
        }

        .pax-name {
            font-weight: 700;
            color: #0f1b2d;
        }

        .pax-sub {
            display: block;
            font-size: 7pt;
            color: #8a97ad;
            margin-top: 0.4mm;
        }

        .masked {
            font-family: 'Geist Mono', monospace;
            letter-spacing: 0.2mm;
        }

        .leg {
            white-space: nowrap;
        }

        .leg .leg-time {
            display: block;
            font-size: 7pt;
            color: #8a97ad;
            margin-top: 0.3mm;
        }

        /* ——— Signatures ——— */
        .signatures {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 4mm 0;
            margin: 8mm -4mm 0;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .signature {
            display: table-cell;
            width: 33%;
            border: 0.25mm solid #d8dee9;
            border-radius: 2mm;
            padding: 3.5mm 4mm 8mm;
            vertical-align: top;
        }

        .signature-title {
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.3mm;
            text-transform: uppercase;
            color: #001d4e;
        }

        .signature-hint {
            font-size: 6.8pt;
            color: #8a97ad;
            margin-top: 0.8mm;
        }

        .signature-line {
            margin-top: 12mm;
            border-top: 0.25mm dashed #b6c0d2;
            padding-top: 1.2mm;
            font-size: 6.8pt;
            color: #8a97ad;
        }

        /* ——— Pied de page ———
           Statique (en fin de document) : un position:fixed serait répété
           par Chromium mais les lignes passeraient dessous sur les pages
           intermédiaires. Le pied de chaque page du PDF final viendra du
           footerTemplate Browsershot (numéros de page natifs). */
        .doc-footer {
            margin: 6mm 10mm 0;
            padding: 2.5mm 0 0;
            border-top: 0.25mm solid #d8dee9;
            font-size: 6.5pt;
            color: #8a97ad;
            display: table;
            width: calc(100% - 20mm);
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .doc-footer-left {
            display: table-cell;
            text-align: left;
        }

        .doc-footer-right {
            display: table-cell;
            text-align: right;
            font-family: 'Geist Mono', monospace;
        }

        .security-note {
            margin-top: 5mm;
            padding: 3mm 4mm;
            border-left: 1mm solid #f6b000;
            background: #fdf8ec;
            font-size: 7pt;
            color: #6b5a20;
            line-height: 1.6;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* ——— Barre d'outils de prévisualisation (écran uniquement,
           jamais imprimée ni rendue dans le PDF) ——— */
        .preview-toolbar {
            display: none;
        }

        @media screen {
            body {
                background: #e8ebf0;
                padding: 0 0 32px;
            }

            .preview-toolbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                position: sticky;
                top: 0;
                z-index: 50;
                background: #ffffff;
                border-bottom: 1px solid #d8dee9;
                box-shadow: 0 2px 12px rgba(0, 29, 78, 0.08);
                padding: 10px 24px;
                margin-bottom: 24px;
            }

            .preview-toolbar .pt-title {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 12.5px;
                font-weight: 700;
                color: #001d4e;
            }

            .preview-toolbar .pt-title small {
                display: block;
                font-size: 10px;
                font-weight: 500;
                color: #8a97ad;
            }

            .preview-toolbar .pt-actions {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .pt-btn {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 8px 16px;
                border-radius: 8px;
                border: none;
                font-family: 'Geist Sans', sans-serif;
                font-size: 12.5px;
                font-weight: 600;
                cursor: pointer;
                text-decoration: none;
            }

            .pt-btn svg {
                width: 15px;
                height: 15px;
            }

            .pt-btn-navy {
                background: #001d4e;
                color: #ffffff;
            }

            .pt-btn-navy:hover {
                background: #0057b8;
            }

            .pt-btn-gold {
                background: #f6b000;
                color: #1a1200;
            }

            .pt-btn-gold:hover {
                background: #d99e00;
            }

            .paper {
                width: 277mm;
                margin: 0 auto;
                background: #ffffff;
                box-shadow: 0 8px 40px rgba(0, 29, 78, 0.14);
                border-radius: 2mm;
                overflow: hidden;
            }
        }
    </style>
</head>

<body>
    {{-- Barre d'outils écran : Imprimer ouvre la boîte de dialogue native,
         Télécharger sert le PDF Browsershot — invisible à l'impression. --}}
    <div class="preview-toolbar">
        <div class="pt-title">
            Aperçu du manifeste
            <small>{{ $document['number'] }} · version {{ $document['version'] }}</small>
        </div>
        <div class="pt-actions">
            <button type="button" class="pt-btn pt-btn-navy" onclick="window.print()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9V2h12v7" />
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                    <rect x="6" y="14" width="12" height="8" />
                </svg>
                Imprimer
            </button>
            @if ($downloadUrl ?? null)
                <a href="{{ $downloadUrl }}" class="pt-btn pt-btn-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    Télécharger le PDF
                </a>
            @endif
        </div>
    </div>

    <div class="paper">
        {{-- Bandeau société — identité légale (settings facturation) + référence document --}}
        <header class="letterhead">
            <div class="letterhead-brand">
                <img src="{{ $logoDataUri }}" alt="{{ $company['name'] }}">
                <div class="letterhead-company">
                    {{ $company['tagline'] }}<br>
                    {{ implode(' · ', $company['addressLines']) }}<br>
                    {{ implode(' · ', $company['contacts']) }}<br>
                    IFU : {{ $company['ifu'] }}@if ($company['nim'])
                        · NIM : {{ $company['nim'] }}
                    @endif
                </div>
            </div>
            <div class="letterhead-doc">
                <div class="doc-type">MANIFESTE</div>
                <div class="doc-ref">N° {{ $document['number'] }}</div>
                <div class="doc-meta">
                    Version {{ $document['version'] }} · Édité le {{ $document['generatedAt'] }}
                </div>
            </div>
        </header>
        <div class="gold-rule"></div>

        <div class="sheet">
            {{-- Titre du document --}}
            <div class="doc-title">
                <div class="doc-title-main">
                    <h1>Manifeste passagers</h1>
                    <p>Liste officielle des voyageurs — contrôle d'embarquement et présentation aux frontières.</p>
                </div>
                <div class="doc-status">
                    <span class="pill pill-navy">{{ $departure['statusLabel'] }}</span>
                </div>
            </div>

            {{-- Identité du départ --}}
            <section class="departure-card">
                <div class="departure-head">
                    <div style="display: table-cell; vertical-align: middle;">
                        <div class="departure-kicker">
                            {{ $departure['lineCode'] }} · {{ $departure['lineTypeLabel'] }}
                        </div>
                        <div class="departure-route">
                            {{ $departure['originCity'] }}<span
                                class="arrow">→</span>{{ $departure['destinationCity'] }}
                        </div>
                        <div class="departure-sub">{{ $departure['lineName'] }}</div>
                    </div>
                    <div class="departure-badge">
                        <span class="pill pill-gold">{{ $departure['lineTypeLabel'] }}</span>
                    </div>
                </div>
                <div class="departure-grid">
                    <div class="row">
                        <div class="cell">
                            <div class="cell-label">Date de départ</div>
                            <div class="cell-value">{{ $departure['dateLabel'] }}</div>
                        </div>
                        <div class="cell">
                            <div class="cell-label">Heure</div>
                            <div class="cell-value">{{ $departure['timeLabel'] }}</div>
                        </div>
                        <div class="cell">
                            <div class="cell-label">Véhicule</div>
                            <div class="cell-value">{{ $departure['busRegistration'] }}</div>
                        </div>
                        <div class="cell">
                            <div class="cell-label">Quai</div>
                            <div class="cell-value">{{ $departure['originPoint'] ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="cell">
                            <div class="cell-label">Conducteur</div>
                            <div class="cell-value">{{ $departure['driverName'] ?? '—' }}</div>
                        </div>
                        <div class="cell">
                            <div class="cell-label">Convoyeur</div>
                            <div class="cell-value">{{ $departure['conductorName'] ?? '—' }}</div>
                        </div>
                        <div class="cell">
                            <div class="cell-label">Arrêts de la ligne</div>
                            <div class="cell-value"><small>{{ $departure['stopsLabel'] }}</small></div>
                        </div>
                        <div class="cell">
                            <div class="cell-label">Capacité</div>
                            <div class="cell-value">{{ $departure['seatCount'] }} <small>sièges</small></div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Compteurs d'embarquement --}}
            <div class="kpi-strip">
                <div class="kpi">
                    <div class="kpi-value">{{ $summary['total'] }}</div>
                    <div class="kpi-label">Passagers au manifeste</div>
                </div>
                <div class="kpi kpi-green">
                    <div class="kpi-value">{{ $summary['boarded'] }}</div>
                    <div class="kpi-label">Embarqués</div>
                </div>
                <div class="kpi kpi-gold">
                    <div class="kpi-value">{{ $summary['pending'] }}</div>
                    <div class="kpi-label">À contrôler</div>
                </div>
                <div class="kpi kpi-red">
                    <div class="kpi-value">{{ $summary['absent'] }}</div>
                    <div class="kpi-label">Absents (no-show)</div>
                </div>
            </div>

            {{-- Liste des passagers — carte façon tables Caisse/Paramétrage --}}
            <section class="table-card">
                <div class="table-card-head">
                    <div class="tc-main">
                        <div class="tc-title">Liste des voyageurs</div>
                        <div class="tc-sub">Montée et descente par voyageur — sièges et pièces d'identité</div>
                    </div>
                    <div class="tc-side">{{ count($passengers) }} passager(s)</div>
                </div>
                <table class="manifest-table">
                    <thead>
                        <tr>
                            <th class="col-num">N°</th>
                            <th class="col-seat">Siège</th>
                            <th>Passager</th>
                            <th>Nationalité</th>
                            <th>Pièce d'identité</th>
                            <th>Naissance</th>
                            <th>Montée</th>
                            <th>Descente</th>
                            <th>Embarquement</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($passengers as $pax)
                            <tr>
                                <td class="col-num">{{ str_pad((string) $pax['index'], 2, '0', STR_PAD_LEFT) }}</td>
                                <td class="col-seat"><span class="seat-code">{{ $pax['seat'] }}</span></td>
                                <td>
                                    <span class="pax-name">{{ $pax['name'] }}</span>
                                    <span class="pax-sub">{{ $pax['ticketRef'] }}@if ($pax['phone'])
                                            · {{ $pax['phone'] }}
                                        @endif
                                    </span>
                                </td>
                                <td>{{ $pax['nationality'] !== '' ? $pax['nationality'] : '—' }}</td>
                                <td>
                                    @if ($pax['documentNumber'] !== '')
                                        @if ($pax['documentLabel'] !== '')
                                            <span class="pax-name"
                                                style="font-weight: 600;">{{ $pax['documentLabel'] }}</span>
                                        @endif
                                        <span class="pax-sub masked">{{ $pax['documentNumber'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $pax['birthDate'] ?? '—' }}</td>
                                <td class="leg">
                                    {{ $pax['originStop'] }}
                                    <span class="leg-time">{{ $pax['originTime'] }}</span>
                                </td>
                                <td class="leg">
                                    {{ $pax['destinationStop'] }}
                                    <span class="leg-time">{{ $pax['destinationTime'] }}</span>
                                </td>
                                <td>
                                    <span
                                        class="pill pill-{{ $pax['boardingTone'] }}">{{ $pax['boardingLabel'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            {{-- Note de conformité --}}
            @if ($international)
                <div class="security-note">
                    <strong>Contrôle frontière.</strong> Sur les lignes internationales, le nom, la nationalité, le
                    numéro de pièce, la montée, la descente et le siège sont obligatoires pour chaque voyageur. Ce
                    document est présenté aux autorités aux points de passage — toute identité incomplète doit être
                    régularisée avant le départ.
                </div>
            @endif

            {{-- Signatures --}}
            <div class="signatures">
                <div class="signature">
                    <div class="signature-title">Le Convoyeur</div>
                    <div class="signature-hint">Contrôle embarquement P501</div>
                    <div class="signature-line">Nom, date et signature</div>
                </div>
                <div class="signature">
                    <div class="signature-title">Le Responsable d'exploitation</div>
                    <div class="signature-hint">Validation du manifeste</div>
                    <div class="signature-line">Nom, date et signature</div>
                </div>
                <div class="signature">
                    <div class="signature-title">Cachet & visa</div>
                    <div class="signature-hint">Autorité au point de passage</div>
                    <div class="signature-line">Observations</div>
                </div>
            </div>
        </div>

        {{-- Pied de page répété sur chaque page --}}
        <footer class="doc-footer">
            <div class="doc-footer-left">
                {{ $company['name'] }} · Manifeste {{ $document['number'] }} — document officiel, toute modification
                est tracée et génère une nouvelle version.
            </div>
            <div class="doc-footer-right">
                v{{ $document['version'] }} · {{ $document['generatedAt'] }}
            </div>
        </footer>
    </div>
</body>

</html>
