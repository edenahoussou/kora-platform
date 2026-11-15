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
            display: inline-flex;
            align-items: center;
            gap: 1.5mm;
            padding: 2.5mm 6mm;
            background: #001d4e;
            color: #ffffff;
            font-size: 2.6mm;
            font-weight: 600;
            text-decoration: none;
            border-radius: 3mm;
            margin: 0 1mm;
        }

        .ticket-btn svg {
            width: 4mm;
            height: 4mm;
            flex-shrink: 0;
        }

        .ticket-btn-gold {
            background: #c8a44d;
            color: #001d4e;
        }

        .ticket-btn-whatsapp {
            background: #25d366;
            color: #ffffff;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }

        .ticket-btn-whatsapp:hover {
            background: #1da851;
        }

        .ticket-btn-outline {
            background: transparent;
            color: #001d4e;
            border: 0.5mm solid #001d4e;
        }

        .ticket-btn-outline:hover {
            background: #001d4e;
            color: #ffffff;
        }

        .ticket-flash {
            text-align: center;
            padding: 2mm 4mm;
            margin: 0 auto 2mm;
            max-width: 72mm;
            font-size: 2.4mm;
            font-weight: 600;
            border-radius: 2mm;
        }

        .ticket-flash-success {
            background: #e6f4ea;
            color: #1b5e20;
            border: 0.5mm solid #25d366;
        }

        .ticket-flash-info {
            background: #e8eaf6;
            color: #1a237e;
            border: 0.5mm solid #001d4e;
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
                        @if (!empty($passengerPhone))
                            <div class="ticket-field">
                                <div class="ticket-field-label">Téléphone</div>
                                <div class="ticket-field-value">{{ $passengerPhone }}</div>
                            </div>
                        @endif
                        @if (!empty($passengerDocument))
                            <div class="ticket-field">
                                <div class="ticket-field-label">Document</div>
                                <div class="ticket-field-value">{{ $passengerDocument }}</div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="ticket-section">
                    <div class="ticket-section-title">Trajet ({{ $legsCount }} jambe{{ $legsCount > 1 ? 's' : '' }})
                    </div>
                    @foreach ($legs as $leg)
                        <div class="ticket-leg">
                            <span class="ticket-leg-direction">{{ $leg['directionLabel'] }}</span>
                            <div class="ticket-leg-route">{{ $leg['originCity'] }} &rarr;
                                {{ $leg['destinationCity'] }}</div>
                            <div class="ticket-leg-details">
                                {{ $leg['lineCode'] }} &middot; Siège {{ $leg['seatCode'] }}<br>
                                Montée
                                @if ($leg['departureAt'])
                                    {{ $leg['dateLabel'] }} à {{ $leg['departureTime'] }}
                                    @if ($leg['isInternational']) ({{ $leg['departureTimezone'] }}) @endif
                                @else
                                    Horaire non disponible
                                @endif<br>

                                Descente
                                @if ($leg['arrivalAt'])
                                    {{ $leg['arrivalDateLabel'] }} à {{ $leg['arrivalTime'] }}
                                    @if ($leg['isInternational']) ({{ $leg['arrivalTimezone'] }}) @endif
                                @else
                                    Horaire non disponible
                                @endif<br>

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
                        @unless ($hideAmount ?? false)
                            @if (!empty($mecefUid))
                                <div class="ticket-field">
                                    <div class="ticket-field-label">N° MECeF</div>
                                    <div class="ticket-field-value">{{ $mecefUid }}</div>
                                </div>
                            @endif
                            <div class="ticket-field">
                                <div class="ticket-field-label">Montant</div>
                                <div class="ticket-field-value">{{ $amountLabel }}
                                    @if (!empty($originalAmountLabel))
                                        <span
                                            style="text-decoration: line-through; opacity: .55; font-size: .8em;">{{ $originalAmountLabel }}</span>
                                    @endif
                                </div>
                            </div>
                            @if (!empty($discountLabel))
                                <div class="ticket-field">
                                    <div class="ticket-field-label">Remise</div>
                                    <div class="ticket-field-value">{{ $discountLabel }}
                                        @if (!empty($discountAmountLabel))
                                            ({{ $discountAmountLabel }})
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endunless
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

            @if (!$isPdf)
                @if (session('whatsapp_status'))
                    @php
                        $status = session('whatsapp_status');
                        $flashClass = $status === 'already_sent' ? 'ticket-flash-info' : 'ticket-flash-success';
                        $flashMsg =
                            $status === 'already_sent'
                                ? 'Billet déjà envoyé via WhatsApp.'
                                : 'Envoi WhatsApp en cours... Le billet sera livré dans quelques instants.';
                    @endphp
                    <div class="ticket-flash {{ $flashClass }}">{{ $flashMsg }}</div>
                @endif

                <div class="ticket-actions">
                    <button class="ticket-btn ticket-btn-gold" onclick="window.print()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z" />
                        </svg>
                        Imprimer
                    </button>
                    <a class="ticket-btn" href="{{ $pdfUrl }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3" />
                        </svg>
                        Télécharger PDF
                    </a>
                    <form method="POST" action="{{ route('tickets.whatsapp', $ticketUuid) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="ticket-btn ticket-btn-whatsapp">
                            <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                            </svg>
                            Envoyer WhatsApp
                        </button>
                    </form>
                    <a class="ticket-btn ticket-btn-outline" href="{{ route('sale') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 3h18v18H3z" />
                            <path d="M3 9h18M9 21V9" />
                        </svg>
                        Nouvelle vente
                    </a>
                </div>
            @endif
        </div>
    </div>
    @if (request()->query('download') && !$isPdf)
        <script>
            window.addEventListener('load', function() {
                window.location.href = '{{ $pdfUrl }}';
            });
        </script>
    @endif
</body>

</html>
