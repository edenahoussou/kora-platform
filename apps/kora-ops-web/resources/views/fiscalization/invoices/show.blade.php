<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice['reference'] ?? 'Facture fiscale' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #fff;
            color: #1a1a1a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            width: 100%;
            min-height: 277mm;
            background: #fff;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 14px;
            border-bottom: 2px solid #001d4e;
        }

        .brand {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: .08em;
            color: #001d4e;
        }

        .brand-sub {
            margin-top: 3px;
            color: #c8a44d;
            font-size: 9px;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .seller-line {
            margin-top: 6px;
            color: #333;
            font-size: 10px;
        }

        .seller-line strong {
            color: #001d4e;
        }

        .invoice-meta {
            text-align: right;
        }

        .invoice-meta h2 {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            color: #001d4e;
        }

        .invoice-meta .meta-line {
            color: #444;
            font-size: 10px;
            padding: 1px 0;
        }

        .invoice-meta .meta-line strong {
            color: #1a1a1a;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin: 16px 0 18px;
        }

        .info-box {
            border: 1px solid #001d4e;
        }

        .info-box-header {
            padding: 4px 8px;
            background: #001d4e;
            color: #fff;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .info-box-content {
            padding: 7px 8px;
        }

        .info-row {
            display: flex;
            margin-bottom: 3px;
        }

        .info-label {
            width: 90px;
            font-style: italic;
            color: #555;
            font-size: 10px;
        }

        .info-value {
            flex: 1;
            font-weight: 600;
            color: #1a1a1a;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .items-table th {
            border: 1px solid #001d4e;
            background: #001d4e;
            color: #fff;
            padding: 6px 8px;
            font-size: 9px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .items-table td {
            border: 1px solid #001d4e;
            padding: 6px 8px;
            vertical-align: top;
        }

        .item-code {
            display: block;
            margin-top: 2px;
            color: #66758a;
            font-size: 9px;
        }

        .tax-group-tag {
            float: right;
            font-size: 9px;
            color: #66758a;
        }

        .section-title {
            margin-bottom: 5px;
            font-style: italic;
            color: #555;
            font-size: 10px;
            text-transform: uppercase;
        }

        .ventilation-table {
            width: 70%;
            border-collapse: collapse;
        }

        .ventilation-table th,
        .ventilation-table td {
            border: 1px solid #8a97a8;
            padding: 4px 8px;
        }

        .ventilation-table th {
            background: #eef2f7;
            color: #001d4e;
            font-size: 9px;
            text-align: left;
            text-transform: uppercase;
        }

        .total-section {
            display: flex;
            justify-content: flex-end;
            margin: -26px 0 18px;
        }

        .total-box {
            border-bottom: 3px solid #c8a44d;
            color: #001d4e;
            font-size: 15px;
            font-weight: 800;
            padding: 6px 12px;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .payment-table th,
        .payment-table td {
            border: 1px solid #8a97a8;
            padding: 4px 8px;
        }

        .payment-table th {
            background: #eef2f7;
            color: #001d4e;
            font-size: 9px;
            text-align: left;
            text-transform: uppercase;
        }

        .amount-words {
            margin: 14px 0;
            font-size: 11px;
        }

        .status-banner {
            margin-bottom: 14px;
            padding: 7px 11px;
            border: 1px solid #d9e0ea;
            border-left: 4px solid #c8a44d;
            background: #f7f9fb;
            font-weight: 700;
            color: #001d4e;
        }

        .security-box {
            position: relative;
            margin-top: 22px;
            border: 1px solid #c8a44d;
            padding: 14px 12px 12px;
        }

        .security-title {
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            padding: 0 10px;
            background: #fff;
            font-style: italic;
            color: #555;
            font-size: 9px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .security-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .qr-code svg {
            width: 30mm;
            height: 30mm;
            display: block;
        }

        .security-details {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-size: 10px;
            line-height: 1.6;
            color: #1a1a1a;
        }

        .invoice-footer {
            margin-top: 26px;
            padding-top: 10px;
            border-top: 1px solid #e1e5ea;
            text-align: center;
            color: #66758a;
            font-size: 8px;
        }

        .screen-actions {
            margin: 18px auto;
            max-width: 880px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .screen-actions a,
        .screen-actions button {
            display: inline-block;
            padding: 9px 14px;
            border: 0;
            border-radius: 6px;
            background: #001d4e;
            color: #fff;
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }

        .screen-actions .btn-secondary {
            background: #fff;
            color: #001d4e;
            border: 1px solid #001d4e;
        }

        .brand-logo {
            display: block;
            max-height: 20mm;
            max-width: 58mm;
            margin-bottom: 3mm;
        }

        @media print {
            body {
                background: #fff;
            }

            .screen-actions {
                display: none;
            }
        }
    </style>
</head>

<body>
    @if (!($isPdf ?? true))
        <nav class="screen-actions" aria-label="Actions de la facture">
            <a href="{{ $backUrl ?? url()->previous() }}" class="btn-secondary">← Retour</a>
            <button type="button" onclick="window.print()">Imprimer</button>
            @if (!empty($pdfUrl))
                <a href="{{ $pdfUrl }}">Télécharger le PDF</a>
            @endif
        </nav>
    @endif
    <main class="page">
        @if (($invoice['status'] ?? null) !== 'certified')
            <div class="status-banner">
                Statut : {{ $invoice['statusLabel'] ?? '—' }}
                @if (!empty($fiscal['errorDescription']))
                    — {{ $fiscal['errorDescription'] }}
                @endif
            </div>
        @endif

        <header class="invoice-header">
            <div>
                @if (!empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="KORA" class="brand-logo">
                @else
                    <div class="brand">KORA</div>
                @endif
                <div class="brand-sub">{{ $invoice['emitter']['shopName'] ?? 'Transport & mobilité' }}</div>
                <div class="seller-line">IFU : <strong>{{ $fiscal['ifu'] ?? '—' }}</strong></div>
                @if (!empty($invoice['emitter']['rccm']))
                    <div class="seller-line">RCCM : <strong>{{ $invoice['emitter']['rccm'] }}</strong></div>
                @endif
            </div>
            <div class="invoice-meta">
                <h2>{{ $invoice['documentTypeLabel'] ?? 'Facture fiscale' }}</h2>
                <div class="meta-line">Facture #
                    <strong>{{ $fiscal['reference'] ?? ($invoice['reference'] ?? '—') }}</strong>
                </div>
                <div class="meta-line">Date :
                    <strong>{{ isset($invoice['finalizedAt']) && $invoice['finalizedAt'] ? \Illuminate\Support\Carbon::parse($invoice['finalizedAt'])->format('d/m/Y H:i') : \Illuminate\Support\Carbon::parse($invoice['createdAt'] ?? 'now')->format('d/m/Y H:i') }}</strong>
                </div>
                @if (($invoice['documentType'] ?? null) === 'FA' && !empty($invoice['originalInvoiceReference']))
                    <div class="meta-line"><strong>Facture d'origine :
                            {{ $invoice['originalInvoiceReference'] }}</strong>
                    </div>
                @endif
                <div class="meta-line">Opérateur :
                    <strong>{{ $fiscal['operatorName'] ?? ($invoice['requestedBy'] ?? '—') }}</strong>
                </div>
                <div class="meta-line">Réf. KORA : <strong>{{ $invoice['koraReference'] ?? '—' }}</strong></div>
            </div>
        </header>

        <div class="info-grid">
            <div class="info-box">
                <div class="info-box-header">Émetteur</div>
                <div class="info-box-content">
                    <div class="info-row">
                        <div class="info-label">Structure</div>
                        <div class="info-value">{{ $invoice['emitter']['shopName'] ?? 'KORA' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Adresse</div>
                        <div class="info-value">{{ $invoice['emitter']['address'] ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Contact</div>
                        <div class="info-value">{{ $invoice['emitter']['contact'] ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">e-MCF (NIM)</div>
                        <div class="info-value">{{ $invoice['emitter']['nim'] ?? ($fiscal['nim'] ?? '—') }}</div>
                    </div>
                </div>
            </div>
            <div class="info-box">
                <div class="info-box-header">Client</div>
                <div class="info-box-content">
                    <div class="info-row">
                        <div class="info-label">Nom</div>
                        <div class="info-value">{{ $fiscal['clientName'] ?? ($invoice['client']['name'] ?? '—') }}
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Contact</div>
                        <div class="info-value">
                            {{ $fiscal['clientContact'] ?? ($invoice['client']['contact'] ?? '—') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">IFU</div>
                        <div class="info-value">{{ $fiscal['clientIfu'] ?? '—' }}</div>
                    </div>
                    @if (!empty($fiscal['clientAddress']))
                        <div class="info-row">
                            <div class="info-label">Adresse</div>
                            <div class="info-value">{{ $fiscal['clientAddress'] }}</div>
                        </div>
                    @endif
                    @if (!empty($invoice['reservationReference']))
                        <div class="info-row">
                            <div class="info-label">Réservation</div>
                            <div class="info-value">{{ $invoice['reservationReference'] }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 34px; text-align: center;">#</th>
                    <th>Désignation</th>
                    <th style="width: 110px; text-align: right;">Prix unitaire (T.T.C.)</th>
                    <th style="width: 70px; text-align: center;">Quantité</th>
                    <th style="width: 110px; text-align: right;">Montant (T.T.C.)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice['items'] ?? [] as $item)
                    <tr>
                        <td style="text-align: center;">{{ $loop->iteration }}</td>
                        <td>
                            {{ $item['name'] }}
                            <span
                                class="tax-group-tag">{{ \App\Modules\Fiscalization\Support\EmecfTaxVentilation::itemTag($item['taxGroup'] ?? null, $item['taxSpecific'] ?? null) }}</span>
                            @if (!empty($item['code']))
                                <span class="item-code">Réf : {{ $item['code'] }}</span>
                            @endif
                            @if ($item['originalPrice'] !== null)
                                <span class="item-code">Prix d'origine :
                                    {{ $formatMoney($item['originalPrice']) }}</span>
                            @endif
                            @if ($item['priceModification'])
                                <span class="item-code">{{ $item['priceModification'] }}</span>
                            @endif
                        </td>
                        <td style="text-align: right;">{{ number_format((float) $item['price'], 0, ',', ' ') }}</td>
                        <td style="text-align: center;">{{ number_format((float) $item['quantity'], 2, ',', ' ') }}
                        </td>
                        <td style="text-align: right;">
                            {{ number_format((float) $item['price'] * (float) $item['quantity'], 0, ',', ' ') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Aucun article fiscal enregistré.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if (!empty($ventilation))
            <div>
                <div class="section-title">--- Ventilation des impôts ---</div>
                <table class="ventilation-table">
                    <thead>
                        <tr>
                            <th>Groupe</th>
                            <th style="text-align: right;">Total</th>
                            <th style="text-align: right;">Imposable</th>
                            <th style="text-align: right;">Impôt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ventilation as $group)
                            <tr>
                                <td>{{ $group['label'] }}</td>
                                <td style="text-align: right;">{{ number_format($group['total'], 0, ',', ' ') }}</td>
                                <td style="text-align: right;">
                                    {{ $group['imposable'] === null ? '-' : number_format($group['imposable'], 0, ',', ' ') }}
                                </td>
                                <td style="text-align: right;">
                                    {{ $group['impot'] === null || $group['impot'] <= 0 ? '-' : number_format($group['impot'], 0, ',', ' ') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="total-section">
            <div class="total-box">Total :
                {{ number_format((float) ($fiscal['taxes']['total'] ?? $invoice['amount']), 0, ',', ' ') }} XOF</div>
        </div>

        <div>
            <div class="section-title">--- Répartition des paiements ---</div>
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>Type de paiement</th>
                        <th style="text-align: right; width: 130px;">Payé</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoice['payments'] ?? [] as $payment)
                        <tr>
                            <td style="text-transform: uppercase;">{{ $payment['label'] ?? $payment['name'] }}</td>
                            <td style="text-align: right;">{{ number_format((float) $payment['amount'], 0, ',', ' ') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">—</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="amount-words">
            Arrêtée la présente facture à la somme de
            <strong>{{ $amountInWords }} francs CFA</strong> TTC.
        </div>

        @if ($qrSvg || !empty($fiscal['codeMecEfDgi']))
            <div class="security-box">
                <div class="security-title">--- Éléments de sécurité de la facture normalisée ---</div>
                <div class="security-content">
                    <div class="qr-code">
                        @if ($qrSvg)
                            {!! $qrSvg !!}
                        @endif
                    </div>
                    <div class="security-details">
                        <div>Code MECeF/DGI : <strong>{{ $fiscal['codeMecEfDgi'] ?? '—' }}</strong></div>
                        <div>MECeF NIM : <strong>{{ $fiscal['nim'] ?? '—' }}</strong></div>
                        <div>MECeF Compteurs : <strong>{{ $fiscal['counters'] ?? '—' }}</strong></div>
                        <div>MECeF Heure : <strong>{{ $fiscal['dateTime'] ?? '—' }}</strong></div>
                    </div>
                </div>
            </div>
        @endif

        <footer class="invoice-footer">
            <span>Document généré par KORA OPS · Montants en XOF</span>
        </footer>
    </main>
</body>

</html>
