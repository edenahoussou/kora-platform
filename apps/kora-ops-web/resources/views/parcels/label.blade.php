<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brand }} — Bordereau {{ $reference }}</title>
    <style>
        :root {
            color-scheme: light;
            --navy: #001d4e;
            --gold: #b48a26;
            --ink: #122033;
            --muted: #637083;
            --line: #c9d1dc;
            --paper: #ffffff;
            --screen: #e9edf2;
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: var(--screen);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar {
            width: min(100%, 297mm);
            margin: 0 auto;
            padding: 14px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .toolbar-copy {
            font-size: 12px;
            line-height: 1.45;
            color: #445064;
        }

        .toolbar-copy strong {
            display: block;
            color: var(--navy);
            font-size: 14px;
        }

        .toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 38px;
            padding: 0 14px;
            border: 1px solid var(--navy);
            border-radius: 6px;
            background: var(--navy);
            color: #fff;
            font: 700 12px/1 Arial, Helvetica, sans-serif;
            text-decoration: none;
            cursor: pointer;
        }

        .button.secondary {
            background: #fff;
            color: var(--navy);
        }

        .sheet {
            width: 297mm;
            height: 210mm;
            margin: 0 auto 40px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 12mm minmax(0, 1fr);
            background: var(--paper);
            box-shadow: 0 18px 48px -24px rgba(0, 29, 78, .55);
            overflow: hidden;
        }

        .panel {
            min-width: 0;
            height: 210mm;
            padding: 8mm 8mm 7mm;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background:
                linear-gradient(180deg, rgba(0, 29, 78, .045), transparent 26mm),
                var(--paper);
            border-top: 4mm solid var(--navy);
        }

        .panel-stub {
            background:
                linear-gradient(180deg, rgba(180, 138, 38, .08), transparent 30mm),
                var(--paper);
        }

        .panel-kicker {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 5mm;
            color: var(--muted);
            font-size: 7.5pt;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .panel-kicker span:last-child {
            flex-shrink: 0;
            border: 1px solid var(--line);
            border-radius: 2mm;
            padding: 1.2mm 2mm;
            color: var(--navy);
            letter-spacing: .08em;
        }

        .brand {
            margin-top: 3mm;
            color: var(--navy);
            font-size: 22pt;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -.03em;
        }

        .brand-rule {
            width: 24mm;
            height: 1.2mm;
            margin-top: 2mm;
            background: var(--gold);
        }

        .document-title {
            margin-top: 4mm;
            color: var(--navy);
            font-size: 14pt;
            font-weight: 900;
            line-height: 1.05;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .document-subtitle {
            margin-top: 1.4mm;
            color: var(--muted);
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .reference {
            margin-top: 5mm;
            color: var(--ink);
            font-family: "Courier New", Courier, monospace;
            font-size: 21pt;
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: -.02em;
            word-break: break-all;
        }

        .route {
            margin-top: 2.5mm;
            display: flex;
            align-items: center;
            gap: 2.4mm;
            color: var(--navy);
            font-size: 15pt;
            font-weight: 900;
            line-height: 1.1;
        }

        .route-arrow {
            color: var(--gold);
            font-size: 14pt;
        }

        .qr-wrap {
            margin: 6mm auto 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .qr-frame {
            padding: 2.4mm;
            border: 0.45mm solid var(--navy);
            background: #fff;
            line-height: 0;
        }

        .qr-frame svg {
            display: block;
            width: 39mm;
            height: 39mm;
        }

        .qr-caption {
            margin-top: 2.4mm;
            color: var(--navy);
            font-size: 8pt;
            font-weight: 900;
            letter-spacing: .16em;
            text-transform: uppercase;
            text-align: center;
        }

        .field-list {
            margin-top: 5mm;
            display: grid;
            gap: 2.3mm;
        }

        .field {
            display: grid;
            grid-template-columns: 28mm minmax(0, 1fr);
            gap: 3mm;
            align-items: start;
            padding-bottom: 2mm;
            border-bottom: 0.25mm solid #e1e6ed;
        }

        .field dt {
            margin: 0;
            color: var(--muted);
            font-size: 7.5pt;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .field dd {
            margin: 0;
            color: var(--ink);
            font-size: 9.5pt;
            font-weight: 700;
            line-height: 1.25;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .field dd.strong {
            font-size: 10.5pt;
        }

        .sticky-footer {
            margin-top: auto;
            padding-top: 3mm;
            border-top: 0.4mm solid var(--navy);
        }

        .scan-note {
            display: flex;
            align-items: center;
            gap: 2.2mm;
            color: var(--navy);
            font-size: 8pt;
            font-weight: 700;
            line-height: 1.35;
        }

        .scan-icon {
            display: grid;
            place-items: center;
            width: 5.5mm;
            height: 5.5mm;
            flex-shrink: 0;
            border: 0.35mm solid var(--navy);
            font-size: 7pt;
            font-weight: 900;
        }

        .cut {
            position: relative;
            background: #fbfcfd;
            border-left: 0.35mm dashed #8390a3;
            border-right: 0.35mm dashed #8390a3;
        }

        .cut::before {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            border-left: 0.35mm dashed #a8b2c1;
            transform: translateX(-50%);
        }

        .scissors {
            position: absolute;
            top: 10mm;
            left: 50%;
            z-index: 2;
            display: grid;
            place-items: center;
            width: 9mm;
            height: 9mm;
            border: 0.35mm dashed #8390a3;
            border-radius: 50%;
            background: #fff;
            color: var(--navy);
            transform: translateX(-50%);
        }

        .scissors svg {
            width: 5mm;
            height: 5mm;
        }

        .cut-label {
            position: absolute;
            top: 22mm;
            left: 50%;
            width: 32mm;
            transform: translateX(-50%) rotate(-90deg);
            transform-origin: center;
            color: #68758a;
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: .13em;
            text-align: center;
            text-transform: uppercase;
        }

        .stub-reference-row {
            margin-top: 4mm;
            display: flex;
            align-items: center;
            gap: 4mm;
        }

        .stub-reference-row .reference {
            margin-top: 0;
            flex: 1;
            font-size: 15pt;
        }

        .stub-qr {
            flex-shrink: 0;
            padding: 1.4mm;
            border: 0.35mm solid var(--navy);
            line-height: 0;
        }

        .stub-qr svg {
            display: block;
            width: 25mm;
            height: 25mm;
        }

        .stub-route {
            margin-top: 3mm;
            padding: 2.4mm 0;
            border-top: 0.25mm solid var(--line);
            border-bottom: 0.25mm solid var(--line);
            color: var(--navy);
            font-size: 11pt;
            font-weight: 900;
            line-height: 1.2;
        }

        .stub-fields {
            margin-top: 4mm;
            display: grid;
            gap: 2.1mm;
        }

        .signature-zone {
            margin-top: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6mm;
            padding-top: 4mm;
            border-top: 0.25mm solid var(--line);
        }

        .signature-line {
            height: 15mm;
            border-bottom: 0.35mm dashed #8d99aa;
        }

        .signature-label {
            margin-top: 1.6mm;
            color: var(--muted);
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .stub-footer {
            margin-top: 3mm;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 4mm;
            color: var(--muted);
            font-size: 6.5pt;
            line-height: 1.4;
        }

        .stub-footer strong {
            color: var(--navy);
        }

        @page {
            size: A4 landscape;
            margin: 0;
        }

        @media print {
            html,
            body {
                width: 297mm;
                height: 210mm;
                margin: 0;
                padding: 0;
                background: #fff;
            }

            .toolbar { display: none !important; }

            .sheet {
                width: 297mm;
                height: 210mm;
                margin: 0;
                box-shadow: none;
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>
@php
    $origin = $route['origin'] ?? '—';
    $destination = $route['destination'] ?? '—';
    $weight = $weight_grams === null ? '—' : str_replace(',', ' ', number_format((int) $weight_grams, 0, ',', ' ')).' g';
    $destinationHub = trim(implode(' · ', array_unique(array_filter([
        $destination_hub['name'] ?? null,
        $destination_hub['city'] ?? null,
    ]))));
@endphp

@if (! ($pdf ?? false))
<div class="toolbar">
    <div class="toolbar-copy">
        <strong>{{ $brand }} · Bordereau d’envoi</strong>
        Deux volets A4 : le gauche se colle sur le colis, le droit reste à l’agence.
    </div>
    <div class="toolbar-actions">
        <a class="button secondary" href="{{ $back_url }}">Retour aux colis</a>
        <a class="button secondary" href="{{ $pdf_url }}">Télécharger le PDF</a>
        <button class="button" type="button" onclick="window.print()">Imprimer le bordereau</button>
    </div>
</div>
@endif

<main class="sheet" aria-label="Bordereau d’envoi {{ $reference }}">
    <section class="panel panel-stick" aria-labelledby="stick-title">
        <div class="panel-kicker">
            <span>Volet terrain</span>
            <span>À coller sur le colis</span>
        </div>

        <div class="brand">{{ $brand }}</div>
        <div class="brand-rule"></div>

        <h1 class="document-title" id="stick-title">Bordereau d’envoi</h1>
        <p class="document-subtitle">Étiquette logistique · ne pas détacher</p>

        <div class="reference">{{ $reference }}</div>

        <div class="route">
            <span>{{ $origin }}</span>
            <span class="route-arrow">→</span>
            <span>{{ $destination }}</span>
        </div>

        <div class="qr-wrap">
            <div class="qr-frame">{!! $qr_svg !!}</div>
            <div class="qr-caption">Scanner pour suivre le colis</div>
        </div>

        <dl class="field-list">
            <div class="field">
                <dt>Hub destination</dt>
                <dd class="strong">{{ $destinationHub !== '' ? $destinationHub : '—' }}</dd>
            </div>
            <div class="field">
                <dt>Destinataire</dt>
                <dd class="strong">{{ $recipient_name }}</dd>
            </div>
            <div class="field">
                <dt>Poids</dt>
                <dd>{{ $weight }}</dd>
            </div>
        </dl>

        <div class="sticky-footer">
            <div class="scan-note">
                <span class="scan-icon" aria-hidden="true">QR</span>
                <span>Scanner au départ, au chargement et à chaque étape du trajet.</span>
            </div>
        </div>
    </section>

    <div class="cut" aria-hidden="true">
        <span class="scissors" aria-label="Découper le bordereau">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="6" cy="6" r="3"></circle>
                <circle cx="6" cy="18" r="3"></circle>
                <path d="M8.1 8.1 19 19"></path>
                <path d="M8.1 15.9 19 5"></path>
            </svg>
        </span>
        <span class="cut-label">Découper ici</span>
    </div>

    <section class="panel panel-stub" aria-labelledby="stub-title">
        <div class="panel-kicker">
            <span>Volet agence</span>
            <span>Souche · ne pas coller</span>
        </div>

        <div class="brand">{{ $brand }}</div>
        <div class="brand-rule"></div>

        <h2 class="document-title" id="stub-title">Bordereau d’envoi</h2>
        <p class="document-subtitle">Souche de classement et de contrôle</p>

        <div class="stub-reference-row">
            <div class="reference">{{ $reference }}</div>
            <div class="stub-qr">{!! $qr_svg !!}</div>
        </div>

        <div class="stub-route">{{ $origin }} → {{ $destination }}</div>

        <dl class="stub-fields">
            <div class="field">
                <dt>Expéditeur</dt>
                <dd>{{ $sender_name }}</dd>
            </div>
            <div class="field">
                <dt>Destinataire</dt>
                <dd>{{ $recipient_name }}</dd>
            </div>
            <div class="field">
                <dt>Contenu déclaré</dt>
                <dd>{{ $contents }}</dd>
            </div>
            <div class="field">
                <dt>Poids</dt>
                <dd>{{ $weight }}</dd>
            </div>
            <div class="field">
                <dt>Généré le</dt>
                <dd>{{ $generated_at_label }}</dd>
            </div>
        </dl>

        <div class="signature-zone">
            <div>
                <div class="signature-line"></div>
                <div class="signature-label">Agent de dépôt</div>
            </div>
            <div>
                <div class="signature-line"></div>
                <div class="signature-label">Visa agence</div>
            </div>
        </div>

        <div class="stub-footer">
            <span>Document généré par <strong>{{ $brand }}</strong>.<br>Conserver cette souche pour le classement et le contrôle.</span>
            <span>{{ $generated_at_label }}</span>
        </div>
    </section>
</main>
</body>
</html>
