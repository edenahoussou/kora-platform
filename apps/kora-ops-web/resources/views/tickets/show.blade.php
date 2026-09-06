<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billet {{ $shortCode }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background: #f4f4f5;
            color: #001d4e;
            -webkit-font-smoothing: antialiased;
        }

        .ticket-page {
            display: table;
            width: 100%;
            min-height: 100vh;
        }

        .ticket-cell {
            display: table-cell;
            vertical-align: middle;
            padding: 16px;
        }

        .ticket {
            width: 80mm;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0, 29, 78, 0.12);
        }

        .ticket-header {
            background: #001d4e;
            color: #ffffff;
            padding: 6mm 4mm;
            text-align: center;
        }

        .ticket-brand {
            font-size: 5mm;
            font-weight: 700;
            letter-spacing: 0.5mm;
        }

        .ticket-brand-sub {
            font-size: 2.4mm;
            font-weight: 400;
            opacity: 0.8;
            margin-top: 1mm;
            letter-spacing: 0.2mm;
        }

        .ticket-status {
            display: inline-block;
            margin-top: 2mm;
            padding: 1mm 3mm;
            background: #c8a44d;
            color: #001d4e;
            font-size: 2.2mm;
            font-weight: 700;
            border-radius: 2mm;
            text-transform: uppercase;
            letter-spacing: 0.3mm;
        }

        .ticket-route {
            padding: 4mm;
            text-align: center;
            border-bottom: 0.3mm dashed #c8a44d;
        }

        .ticket-route-row {
            display: table;
            width: 100%;
        }

        .ticket-route-origin,
        .ticket-route-arrow,
        .ticket-route-destination {
            display: table-cell;
            vertical-align: middle;
        }

        .ticket-route-origin {
            text-align: left;
        }

        .ticket-route-destination {
            text-align: right;
        }

        .ticket-route-arrow {
            text-align: center;
            width: 14mm;
            color: #c8a44d;
            font-size: 4mm;
            font-weight: 700;
        }

        .ticket-route-city {
            font-size: 4.5mm;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.2mm;
        }

        .ticket-route-label {
            font-size: 2mm;
            color: #6b7280;
            margin-top: 0.5mm;
        }

        .ticket-section {
            padding: 3mm 4mm;
            border-bottom: 0.2mm solid #e5e7eb;
        }

        .ticket-section:last-of-type {
            border-bottom: none;
        }

        .ticket-section-title {
            font-size: 2mm;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.3mm;
            margin-bottom: 2mm;
        }

        .ticket-leg {
            margin-bottom: 2.5mm;
            padding-bottom: 2.5mm;
            border-bottom: 0.15mm dotted #d1d5db;
        }

        .ticket-leg:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .ticket-leg-direction {
            display: inline-block;
            padding: 0.5mm 2mm;
            background: #f0f4ff;
            color: #001d4e;
            font-size: 1.8mm;
            font-weight: 700;
            border-radius: 1mm;
            text-transform: uppercase;
            margin-bottom: 1.5mm;
        }

        .ticket-leg-route {
            font-size: 3mm;
            font-weight: 600;
            margin-bottom: 1mm;
        }

        .ticket-leg-details {
            font-size: 2.2mm;
            color: #4b5563;
            line-height: 1.5;
        }

        .ticket-fields {
            display: table;
            width: 100%;
        }

        .ticket-field {
            display: table-row;
        }

        .ticket-field-label,
        .ticket-field-value {
            display: table-cell;
            padding: 1mm 0;
            font-size: 2.4mm;
        }

        .ticket-field-label {
            color: #6b7280;
            width: 40%;
        }

        .ticket-field-value {
            font-weight: 600;
            text-align: right;
        }

        .ticket-qr {
            text-align: center;
            padding: 3mm 4mm 4mm;
            background: #fafafa;
        }

        .ticket-qr svg {
            width: 32mm;
            height: 32mm;
        }

        .ticket-qr-code {
            font-size: 3mm;
            font-weight: 700;
            letter-spacing: 0.5mm;
            margin-top: 1.5mm;
            color: #001d4e;
        }

        .ticket-footer {
            padding: 3mm 4mm;
            background: #f9fafb;
            text-align: center;
        }

        .ticket-footer-text {
            font-size: 1.9mm;
            color: #6b7280;
            line-height: 1.5;
        }

        .ticket-footer strong {
            color: #001d4e;
        }

        .ticket-actions {
            text-align: center;
            padding: 4mm;
        }

        .ticket-btn {
            display: inline-block;
            padding: 2.5mm 6mm;
            background: #001d4e;
            color: #ffffff;
            font-size: 2.6mm;
            font-weight: 600;
            text-decoration: none;
            border-radius: 3mm;
            margin: 0 1mm;
        }

        .ticket-btn-gold {
            background: #c8a44d;
            color: #001d4e;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .ticket-page {
                display: block;
                min-height: 0;
            }

            .ticket-cell {
                display: block;
                padding: 0;
            }

            .ticket {
                width: 80mm;
                margin: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .ticket-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="ticket-page">
        <div class="ticket-cell">
            <article class="ticket">
                <header class="ticket-header">
                    <div class="ticket-brand">KORA EXPRESS</div>
                    <div class="ticket-brand-sub">Billet de bus &middot; Boarding pass</div>
                    <span class="ticket-status">{{ $statusLabel }}</span>
                </header>

                <div class="ticket-route">
                    <div class="ticket-route-row">
                        <div class="ticket-route-origin">
                            <div class="ticket-route-city">{{ $originCity }}</div>
                            <div class="ticket-route-label">Départ</div>
                        </div>
                        <div class="ticket-route-arrow">&rarr;</div>
                        <div class="ticket-route-destination">
                            <div class="ticket-route-city">{{ $destinationCity }}</div>
                            <div class="ticket-route-label">Arrivée</div>
                        </div>
                    </div>
                </div>

                <section class="ticket-section">
                    <div class="ticket-section-title">Passager</div>
                    <div class="ticket-fields">
                        <div class="ticket-field">
                            <div class="ticket-field-label">Nom</div>
                            <div class="ticket-field-value">{{ $passengerName }}</div>
                        </div>
                        @if (! empty($passengerPhone))
                            <div class="ticket-field">
                                <div class="ticket-field-label">Téléphone</div>
                                <div class="ticket-field-value">{{ $passengerPhone }}</div>
                            </div>
                        @endif
                        @if (! empty($passengerDocument))
                            <div class="ticket-field">
                                <div class="ticket-field-label">Document</div>
                                <div class="ticket-field-value">{{ $passengerDocument }}</div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="ticket-section">
                    <div class="ticket-section-title">Trajet ({{ $legsCount }} jambe{{ $legsCount > 1 ? 's' : '' }})</div>
                    @foreach ($legs as $leg)
                        <div class="ticket-leg">
                            <span class="ticket-leg-direction">{{ $leg['directionLabel'] }}</span>
                            <div class="ticket-leg-route">{{ $leg['originCity'] }} &rarr; {{ $leg['destinationCity'] }}</div>
                            <div class="ticket-leg-details">
                                {{ $leg['dateLabel'] }} &middot; {{ $leg['lineCode'] }}<br>
                                Départ {{ $leg['departureTime'] }} &middot; Siège {{ $leg['seatCode'] }}<br>
                                Bus {{ $leg['busRegistration'] }}
                            </div>
                        </div>
                    @endforeach
                </section>

                <section class="ticket-section">
                    <div class="ticket-section-title">Informations</div>
                    <div class="ticket-fields">
                        <div class="ticket-field">
                            <div class="ticket-field-label">N° billet</div>
                            <div class="ticket-field-value">{{ $shortCode }}</div>
                        </div>
                        @if (! empty($mecefUid))
                            <div class="ticket-field">
                                <div class="ticket-field-label">N° MECeF</div>
                                <div class="ticket-field-value">{{ $mecefUid }}</div>
                            </div>
                        @endif
                        <div class="ticket-field">
                            <div class="ticket-field-label">Montant</div>
                            <div class="ticket-field-value">{{ $amountLabel }}</div>
                        </div>
                        <div class="ticket-field">
                            <div class="ticket-field-label">Émis le</div>
                            <div class="ticket-field-value">{{ $issuedAtLabel }}</div>
                        </div>
                    </div>
                </section>

                <div class="ticket-qr">
                    {!! $qrSvg !!}
                    <div class="ticket-qr-code">{{ $shortCode }}</div>
                </div>

                <footer class="ticket-footer">
                    <div class="ticket-footer-text">
                        <strong>{{ $agencyName ?? 'KORA EXPRESS' }}</strong> &middot; {{ $channelLabel }}<br>
                        Présentez ce billet au montage. Le QR code est vérifiable hors ligne.
                    </div>
                </footer>
            </article>

            @if (! $isPdf)
                <div class="ticket-actions">
                    <button class="ticket-btn ticket-btn-gold" onclick="window.print()">Imprimer</button>
                    <a class="ticket-btn" href="{{ $pdfUrl }}">Télécharger PDF</a>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
