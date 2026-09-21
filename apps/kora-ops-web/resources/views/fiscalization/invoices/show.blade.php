<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice['reference'] ?? 'Facture fiscale' }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f5f7; color: #0d2547; font-family: Arial, Helvetica, sans-serif; font-size: 10px; -webkit-font-smoothing: antialiased; }
        .page { width: 100%; min-height: 277mm; background: #fff; border: 1px solid #dfe3e8; }
        .header { display: flex; justify-content: space-between; gap: 24px; padding: 22px 24px 18px; background: #001d4e; color: #fff; }
        .brand { font-size: 24px; font-weight: 800; letter-spacing: .08em; }
        .brand-sub { margin-top: 4px; color: #d8c27a; font-size: 9px; letter-spacing: .16em; text-transform: uppercase; }
        .header-right { text-align: right; }
        .type { display: inline-block; padding: 5px 10px; border-radius: 999px; background: #c8a44d; color: #001d4e; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .header-reference { margin-top: 8px; font-size: 16px; font-weight: 800; }
        .header-meta { margin-top: 3px; color: #cad5e4; font-size: 9px; }
        .content { padding: 20px 24px 24px; }
        .status { margin-bottom: 16px; padding: 8px 11px; border: 1px solid #d9e0ea; border-left: 4px solid #c8a44d; background: #f7f9fb; font-weight: 700; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .panel { border: 1px solid #e1e5ea; border-radius: 8px; overflow: hidden; }
        .panel-title { padding: 8px 11px; background: #f3f5f7; color: #526174; font-size: 8px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
        .panel-body { padding: 11px; }
        .field { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; border-bottom: 1px dotted #e4e7eb; }
        .field:last-child { border-bottom: 0; }
        .field span:first-child { color: #66758a; }
        .field strong { color: #0d2547; text-align: right; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th { padding: 9px 8px; background: #001d4e; color: #fff; font-size: 8px; text-align: left; text-transform: uppercase; letter-spacing: .08em; }
        td { padding: 9px 8px; border-bottom: 1px solid #e6e9ed; vertical-align: top; }
        td.num, th.num { text-align: right; }
        .item-name { font-weight: 700; }
        .item-meta { margin-top: 3px; color: #6b7786; font-size: 8px; }
        .summary { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 18px; }
        .total-box { padding: 14px; background: #eef3fa; border: 1px solid #d4deec; border-radius: 8px; }
        .total-row { display: flex; justify-content: space-between; padding: 5px 0; }
        .grand-total { margin-top: 5px; padding-top: 9px; border-top: 2px solid #001d4e; font-size: 16px; font-weight: 800; }
        .qr-box { padding: 14px; border: 1px solid #e1e5ea; border-radius: 8px; text-align: center; }
        .qr-box svg { width: 38mm; height: 38mm; }
        .qr-label { margin-top: 7px; color: #526174; font-size: 8px; }
        .footer { display: flex; justify-content: space-between; gap: 20px; margin-top: 18px; padding-top: 12px; border-top: 1px solid #e1e5ea; color: #66758a; font-size: 8px; }
        .screen-actions { margin: 18px auto; max-width: 880px; text-align: center; }
        .screen-actions a { display: inline-block; padding: 9px 14px; border-radius: 6px; background: #001d4e; color: #fff; text-decoration: none; }
        @media print { body { background: #fff; } .screen-actions { display: none; } }
    </style>
</head>

<body>
    <main class="page">
        <header class="header">
            <div>
                <div class="brand">KORA</div>
                <div class="brand-sub">Transport & mobilité</div>
            </div>
            <div class="header-right">
                <div class="type">{{ $invoice['documentTypeLabel'] ?? 'Facture fiscale' }}</div>
                <div class="header-reference">{{ $invoice['reference'] ?? '—' }}</div>
                <div class="header-meta">Référence KORA : {{ $invoice['koraReference'] ?? '—' }}</div>
            </div>
        </header>

        <section class="content">
            <div class="status">Statut : {{ $invoice['statusLabel'] ?? '—' }} @if(!empty($fiscal['statusLabel'])) · e‑MECeF : {{ $fiscal['statusLabel'] }} @endif</div>

            <div class="grid">
                <section class="panel">
                    <div class="panel-title">Émetteur et opérateur</div>
                    <div class="panel-body">
                        <div class="field"><span>Référence facture API</span><strong>{{ $fiscal['reference'] ?? '—' }}</strong></div>
                        <div class="field"><span>IFU</span><strong>{{ $fiscal['ifu'] ?? '—' }}</strong></div>
                        <div class="field"><span>AIB</span><strong>{{ $fiscal['aib'] ?? '—' }}</strong></div>
                        <div class="field"><span>Opérateur</span><strong>{{ $fiscal['operatorName'] ?? '—' }}</strong></div>
                        <div class="field"><span>Identifiant opérateur</span><strong>{{ $fiscal['operatorId'] ?? '—' }}</strong></div>
                    </div>
                </section>
                <section class="panel">
                    <div class="panel-title">Client et réservation</div>
                    <div class="panel-body">
                        <div class="field"><span>Client</span><strong>{{ $fiscal['clientName'] ?? ($invoice['client']['name'] ?? '—') }}</strong></div>
                        <div class="field"><span>IFU client</span><strong>{{ $fiscal['clientIfu'] ?? '—' }}</strong></div>
                        <div class="field"><span>Contact</span><strong>{{ $fiscal['clientContact'] ?? ($invoice['client']['contact'] ?? '—') }}</strong></div>
                        <div class="field"><span>Réservation</span><strong>{{ $invoice['reservationReference'] ?? '—' }}</strong></div>
                    </div>
                </section>
            </div>

            <table>
                <thead>
                    <tr><th>Article</th><th>Taxe</th><th class="num">Qté</th><th class="num">Prix unitaire</th><th class="num">Total</th></tr>
                </thead>
                <tbody>
                    @forelse($invoice['items'] ?? [] as $item)
                        <tr>
                            <td><div class="item-name">{{ $item['name'] }}</div><div class="item-meta">Code : {{ $item['code'] ?? '—' }} @if($item['originalPrice'] !== null) · Prix d’origine : {{ $formatMoney($item['originalPrice']) }} @endif @if($item['priceModification']) · {{ $item['priceModification'] }} @endif</div></td>
                            <td>{{ $item['taxGroup'] ?? '—' }} @if($item['taxSpecific'] !== null) · {{ $item['taxSpecific'] }} @endif</td>
                            <td class="num">{{ $item['quantity'] }}</td>
                            <td class="num">{{ $formatMoney($item['price']) }}</td>
                            <td class="num">{{ $formatMoney($item['price'] * $item['quantity']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucun item fiscal enregistré.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="summary">
                <section class="total-box">
                    <div class="panel-title" style="padding:0 0 7px;background:transparent;">Totaux et taxation</div>
                    @foreach(['ta','tb','tc','td','taa','tab','tac','tad','tae','taf','hab','had','vab','vad','aibAmount','ts'] as $taxKey)
                        @if(($fiscal['taxes'][$taxKey] ?? 0) != 0)<div class="total-row"><span>{{ strtoupper($taxKey) }}</span><strong>{{ $formatMoney($fiscal['taxes'][$taxKey]) }}</strong></div>@endif
                    @endforeach
                    <div class="total-row grand-total"><span>TOTAL</span><strong>{{ $formatMoney($fiscal['taxes']['total'] ?? $invoice['amount']) }}</strong></div>
                </section>
                <section class="qr-box">
                    @if($qrSvg){!! $qrSvg !!}@else<div style="padding:35px 0;color:#66758a;">QR e‑MECeF non disponible</div>@endif
                    <div class="qr-label">Code MECeF/DGI : {{ $fiscal['codeMecEfDgi'] ?? '—' }}</div>
                    <div class="qr-label">NIM : {{ $fiscal['nim'] ?? '—' }} · Compteurs : {{ $fiscal['counters'] ?? '—' }}</div>
                </section>
            </div>

            <section class="panel" style="margin-top:18px;">
                <div class="panel-title">Paiements et informations de certification</div>
                <div class="panel-body">
                    @forelse($invoice['payments'] ?? [] as $payment)<div class="field"><span>{{ $payment['label'] ?? $payment['name'] }}</span><strong>{{ $formatMoney($payment['amount']) }}</strong></div>@empty<div class="field"><span>Paiement</span><strong>—</strong></div>@endforelse
                    <div class="field"><span>Date/heure MECeF</span><strong>{{ $fiscal['dateTime'] ?? '—' }}</strong></div>
                    <div class="field"><span>UID MECeF</span><strong>{{ $fiscal['uid'] ?? ($invoice['mecefUid'] ?? '—') }}</strong></div>
                </div>
            </section>

            <footer class="footer"><span>Document généré par KORA OPS · XOF</span><span>{{ $invoice['createdAt'] ?? '—' }}</span></footer>
        </section>
    </main>
</body>
</html>
