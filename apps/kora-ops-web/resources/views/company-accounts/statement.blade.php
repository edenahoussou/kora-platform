<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>{{ $document['reference'] }} — Relevé de facturation</title>
    <style>
        @php $font =static fn (string $file): string=>'data:font/woff2;base64,' .base64_encode(file_get_contents(resource_path("fonts/geist/{$file}")));
        $money =static fn (int $amount): string=>number_format($amount, 0, ',', ' ') . ' F CFA';
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
            padding: 8mm 12mm 7mm;
            display: table;
            width: 100%;
        }

        .letterhead-brand {
            display: table-cell;
            vertical-align: middle;
            width: 55%;
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
            width: 45%;
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
            padding: 8mm 12mm 0;
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
            font-size: 17pt;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #001d4e;
        }

        .doc-title p {
            margin-top: 1mm;
            font-size: 8pt;
            color: #33415c;
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

        .pill-muted {
            background: #eef1f7;
            color: #33415c;
        }

        /* ——— Cartes identité ——— */
        .identity-grid {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 4mm 0;
            margin: 0 -4mm 5mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .identity-card {
            display: table-cell;
            width: 50%;
            border: 0.25mm solid #d8dee9;
            border-radius: 2.5mm;
            overflow: hidden;
            vertical-align: top;
        }

        .identity-head {
            background: #f4f6f8;
            padding: 2.6mm 4.5mm;
            border-bottom: 0.25mm solid #d8dee9;
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: 0.35mm;
            text-transform: uppercase;
            color: #0057b8;
        }

        .identity-body {
            padding: 3mm 4.5mm;
        }

        .identity-name {
            font-size: 11pt;
            font-weight: 800;
            color: #001d4e;
            letter-spacing: -0.01em;
        }

        .identity-ref {
            margin-top: 0.8mm;
            font-family: 'Geist Mono', monospace;
            font-size: 7.5pt;
            color: #8a5f00;
        }

        .identity-lines {
            margin-top: 1.8mm;
            font-size: 7.5pt;
            line-height: 1.7;
            color: #33415c;
        }

        .identity-lines strong {
            color: #0f1b2d;
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
            width: 20%;
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
            font-size: 13pt;
            font-weight: 800;
            color: #001d4e;
            letter-spacing: -0.02em;
        }

        .kpi-value em {
            font-style: normal;
            font-size: 7.5pt;
            font-weight: 600;
            color: #33415c;
        }

        .kpi-label {
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: 0.3mm;
            text-transform: uppercase;
            color: #33415c;
            margin-top: 0.6mm;
        }

        .kpi.kpi-green .kpi-value {
            color: #157a4c;
        }

        .kpi.kpi-red .kpi-value {
            color: #b42323;
        }

        .kpi.kpi-gold {
            background: #001d4e;
            border-bottom-color: #001d4e;
        }

        .kpi.kpi-gold .kpi-value {
            color: #f6b000;
        }

        .kpi.kpi-gold .kpi-label {
            color: rgba(255, 255, 255, 0.66);
        }

        /* ——— Table des opérations ——— */
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
            color: #33415c;
            margin-top: 0.5mm;
        }

        .table-card-head .tc-side {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-family: 'Geist Mono', monospace;
            font-size: 7pt;
            color: #33415c;
        }

        .lines-table {
            width: 100%;
            border-collapse: collapse;
        }

        .lines-table thead {
            display: table-header-group;
        }

        .lines-table td,
        .lines-table th {
            orphans: 3;
            widows: 3;
        }

        .lines-table th {
            background: #f4f6f8;
            color: #33415c;
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: 0.35mm;
            text-transform: uppercase;
            text-align: left;
            padding: 2.4mm 3mm;
            border-bottom: 0.25mm solid #e4e9f1;
        }

        .lines-table td {
            padding: 2.4mm 3mm;
            border-bottom: 0.2mm solid #edf0f5;
            font-size: 8pt;
            vertical-align: middle;
        }

        .lines-table tbody tr:last-child td {
            border-bottom: none;
        }

        .lines-table tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* Avoir : toute la ligne est teintée vert clair, filet à gauche */
        .lines-table tr.line-refund td {
            background: #e8f6ee;
            border-bottom-color: #cfe8da;
        }

        .lines-table tr.line-refund td:first-child {
            border-left: 0.9mm solid #157a4c;
        }

        .lines-table tr.line-refund .tx-ref,
        .lines-table tr.line-refund .res-ref,
        .lines-table tr.line-refund .col-num,
        .lines-table tr.line-refund .col-date,
        .lines-table tr.line-refund .res-sub {
            color: #157a4c;
        }

        .col-num {
            width: 7mm;
            color: #33415c;
            font-size: 7.5pt;
        }

        .col-date {
            white-space: nowrap;
            color: #0f1b2d;
            font-size: 7.5pt;
        }

        .tx-ref {
            font-family: 'Geist Mono', monospace;
            font-size: 7.5pt;
            font-weight: 500;
            color: #001d4e;
        }

        .res-ref {
            font-weight: 700;
            color: #0f1b2d;
        }

        .res-sub {
            display: block;
            font-size: 7pt;
            font-weight: 600;
            color: #0f1b2d;
            margin-top: 0.4mm;
        }

        .inv-ref {
            font-family: 'Geist Mono', monospace;
            font-size: 7.5pt;
            color: #0057b8;
        }

        .amount {
            font-weight: 800;
            color: #001d4e;
            white-space: nowrap;
            text-align: right;
        }

        .amount.amount-refund {
            color: #157a4c;
        }

        /* ——— Totaux ——— */
        .totals-wrap {
            display: table;
            width: 100%;
            margin-top: 5mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .totals-spacer {
            display: table-cell;
            width: 55%;
        }

        .totals {
            display: table-cell;
            width: 45%;
            border: 0.25mm solid #d8dee9;
            border-radius: 2.5mm;
            overflow: hidden;
        }

        .totals-row {
            display: table;
            width: 100%;
            border-bottom: 0.2mm solid #edf0f5;
        }

        .totals-row>div {
            display: table-cell;
            padding: 2.4mm 4.5mm;
            font-size: 8pt;
        }

        .totals-row .t-label {
            color: #33415c;
        }

        .totals-row .t-value {
            text-align: right;
            font-weight: 700;
            color: #0f1b2d;
        }

        .totals-net {
            background: #001d4e;
        }

        .totals-net .t-label {
            color: rgba(255, 255, 255, 0.72);
            font-weight: 700;
            letter-spacing: 0.25mm;
            text-transform: uppercase;
            font-size: 7pt;
        }

        .totals-net .t-value {
            color: #f6b000;
            font-weight: 800;
            font-size: 10.5pt;
        }

        /* ——— Mentions ——— */
        .legal-note {
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

        /* ——— Pied de page ——— */
        .doc-footer {
            margin: 6mm 12mm 0;
            padding: 2.5mm 0 0;
            border-top: 0.25mm solid #d8dee9;
            font-size: 6.5pt;
            color: #33415c;
            display: table;
            width: calc(100% - 24mm);
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
    </style>
</head>

<body>
    <div class="paper">
        {{-- Bandeau société — identité légale KORA + référence du relevé --}}
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
                <div class="doc-type">RELEVÉ</div>
                <div class="doc-ref">N° {{ $document['reference'] }}</div>
                <div class="doc-meta">
                    Période {{ $statement['periodLabel'] }} · Édité le {{ $document['generatedAt'] }}
                </div>
            </div>
        </header>
        <div class="gold-rule"></div>

        <div class="sheet">
            {{-- Titre du document --}}
            <div class="doc-title">
                <div class="doc-title-main">
                    <h1>Relevé de facturation mensuel</h1>
                    <p>Consolidation des achats au débit du compte organisationnel — règlement à terme.</p>
                </div>
                <div class="doc-status">
                    <span class="pill pill-{{ $document['statusTone'] }}">{{ $document['statusLabel'] }}</span>
                </div>
            </div>

            {{-- Identité du compte client + émission --}}
            <div class="identity-grid">
                <div class="identity-card">
                    <div class="identity-head">Compte client</div>
                    <div class="identity-body">
                        <div class="identity-name">{{ $account['name'] }}</div>
                        <div class="identity-ref">{{ $account['reference'] }} · {{ $account['typeLabel'] }}</div>
                        <div class="identity-lines">
                            @foreach ($account['addressLines'] as $line)
                                {{ $line }}<br>
                            @endforeach
                            @if ($account['ifu'])
                                IFU : <strong>{{ $account['ifu'] }}</strong>
                                @if ($account['rccm'])
                                    · RCCM : <strong>{{ $account['rccm'] }}</strong>
                                @endif
                                <br>
                            @endif
                            @if ($account['email'])
                                {{ $account['email'] }}<br>
                            @endif
                            @if ($account['phone'])
                                {{ $account['phone'] }}
                            @endif
                        </div>
                    </div>
                </div>

                <div class="identity-card">
                    <div class="identity-head">Émission</div>
                    <div class="identity-body">
                        <div class="identity-lines">
                            Période consolidée : <strong>{{ $statement['periodLabel'] }}</strong><br>
                            Généré le : <strong>{{ $document['generatedAt'] }}</strong><br>
                            @if ($document['emailedAt'])
                                Transmis au référent le : <strong>{{ $document['emailedAt'] }}</strong><br>
                            @endif
                            Devise : <strong>{{ $statement['currency'] }}</strong><br>
                            Mode de règlement : <strong>Facturation mensuelle à terme échu</strong>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Compteurs --}}
            <div class="kpi-strip">
                <div class="kpi">
                    <div class="kpi-value">{{ $statement['reservationsCount'] }}</div>
                    <div class="kpi-label">Réservations</div>
                </div>
                <div class="kpi">
                    <div class="kpi-value">{{ $statement['invoicesCount'] }}</div>
                    <div class="kpi-label">Factures normalisées</div>
                </div>
                <div class="kpi">
                    <div class="kpi-value">{{ $money($statement['debitTotal']) }}</div>
                    <div class="kpi-label">Total débits</div>
                </div>
                <div class="kpi kpi-green">
                    <div class="kpi-value">{{ $money($statement['refundTotal']) }}</div>
                    <div class="kpi-label">Avoirs</div>
                </div>
                <div class="kpi kpi-gold">
                    <div class="kpi-value">{{ $money($statement['netTotal']) }}</div>
                    <div class="kpi-label">Net à régler</div>
                </div>
            </div>

            {{-- Opérations --}}
            <div class="table-card">
                <div class="table-card-head">
                    <div class="tc-main">
                        <div class="tc-title">Opérations de la période</div>
                        <div class="tc-sub">Débits d'achats et avoirs imputés au compte</div>
                    </div>
                    <div class="tc-side">{{ count($lines) }} ligne{{ count($lines) > 1 ? 's' : '' }}</div>
                </div>
                <table class="lines-table">
                    <thead>
                        <tr>
                            <th class="col-num">N°</th>
                            <th>Date</th>
                            <th>Référence</th>
                            <th>Type</th>
                            <th>Réservation</th>
                            <th>Facture</th>
                            <th style="text-align: right;">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lines as $index => $line)
                            <tr{{ $line['type'] === 'refund' ? ' class=line-refund' : '' }}>
                                <td class="col-num">{{ $index + 1 }}</td>
                                <td class="col-date">{{ $line['occurredAt'] }}</td>
                                <td><span class="tx-ref">{{ $line['reference'] }}</span></td>
                                <td><span class="pill pill-{{ $line['typeTone'] }}">{{ $line['typeLabel'] }}</span>
                                </td>
                                <td>
                                    @if ($line['reservation'])
                                        <span class="res-ref">{{ $line['reservation']['reference'] }}</span>
                                        <span class="res-sub">{{ $line['reservation']['itinerary'] }} ·
                                            {{ $line['reservation']['passengers'] }}
                                            voyageur{{ $line['reservation']['passengers'] > 1 ? 's' : '' }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($line['invoice'])
                                        <span class="inv-ref">{{ $line['invoice']['koraReference'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="amount{{ $line['type'] === 'refund' ? ' amount-refund' : '' }}">
                                    {{ $line['type'] === 'refund' ? '− ' : '' }}{{ $money($line['amount']) }}
                                </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #33415c; padding: 8mm;">
                                        Aucune opération enregistrée sur la période.
                                    </td>
                                </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Totaux --}}
            <div class="totals-wrap">
                <div class="totals-spacer"></div>
                <div class="totals">
                    <div class="totals-row">
                        <div class="t-label">Total débits</div>
                        <div class="t-value">{{ $money($statement['debitTotal']) }}</div>
                    </div>
                    <div class="totals-row">
                        <div class="t-label">Avoirs & remboursements</div>
                        <div class="t-value">− {{ $money($statement['refundTotal']) }}</div>
                    </div>
                    <div class="totals-row totals-net">
                        <div class="t-label">Net à régler</div>
                        <div class="t-value">{{ $money($statement['netTotal']) }}</div>
                    </div>
                </div>
            </div>

            {{-- Mention légale --}}
            <div class="legal-note">
                Les factures normalisées unitaires (MECeF) demeurent les pièces légales de chaque opération.
                Le présent relevé consolide les achats au débit du compte organisationnel pour un règlement
                à terme échu, conformément aux conditions commerciales du contrat cadre.
            </div>

            {{-- Pied de page --}}
            <footer class="doc-footer">
                <div class="doc-footer-left">
                    {{ $company['name'] }} — Relevé {{ $document['reference'] }} · {{ $statement['periodLabel'] }}
                </div>
                <div class="doc-footer-right">
                    Document généré par KORA OPS
                </div>
            </footer>
        </div>
    </div>
</body>

</html>
