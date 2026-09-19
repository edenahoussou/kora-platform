<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manifeste frontière — {{ $lineCode }} — {{ $dateLabel }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #001d4e;
            -webkit-font-smoothing: antialiased;
        }

        .header {
            background: #001d4e;
            color: #ffffff;
            padding: 5mm 6mm;
            border-radius: 2mm;
            margin-bottom: 5mm;
        }

        .brand {
            font-size: 14pt;
            font-weight: 700;
            letter-spacing: 0.4mm;
        }

        .title {
            font-size: 11pt;
            margin-top: 1mm;
        }

        .meta {
            display: table;
            width: 100%;
            margin-bottom: 5mm;
            border-collapse: collapse;
        }

        .meta-row {
            display: table-row;
        }

        .meta-cell {
            display: table-cell;
            padding: 1.5mm 2mm;
            border: 0.2mm solid #c9d1e0;
            width: 25%;
        }

        .meta-label {
            font-size: 7.5pt;
            text-transform: uppercase;
            color: #5b6b8c;
        }

        .meta-value {
            font-size: 10.5pt;
            font-weight: 600;
        }

        table.passengers {
            width: 100%;
            border-collapse: collapse;
        }

        table.passengers th {
            background: #eef1f7;
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
            padding: 2mm;
            border: 0.2mm solid #c9d1e0;
        }

        table.passengers td {
            padding: 2mm;
            border: 0.2mm solid #c9d1e0;
            font-size: 9.5pt;
            vertical-align: top;
        }

        table.passengers tr {
            page-break-inside: avoid;
        }

        .footer {
            margin-top: 6mm;
            font-size: 8pt;
            color: #5b6b8c;
        }

        .num {
            width: 8mm;
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="brand">KORA EXPRESS</div>
        <div class="title">Manifeste {{ $international ? 'frontière' : 'passagers' }}</div>
    </div>

    <div class="meta">
        <div class="meta-row">
            <div class="meta-cell">
                <div class="meta-label">N° de départ</div>
                <div class="meta-value">{{ $departureNumber }}</div>
            </div>
            <div class="meta-cell">
                <div class="meta-label">Date</div>
                <div class="meta-value">{{ $dateLabel }} {{ $timeLabel }}</div>
            </div>
            <div class="meta-cell">
                <div class="meta-label">Ligne</div>
                <div class="meta-value">{{ $lineCode }}</div>
            </div>
            <div class="meta-cell">
                <div class="meta-label">Passagers</div>
                <div class="meta-value">{{ $passengerCount }}</div>
            </div>
        </div>
        <div class="meta-row">
            <div class="meta-cell" style="width: 50%" colspan="2">
                <div class="meta-label">Trajet</div>
                <div class="meta-value">{{ $lineName }}</div>
            </div>
            <div class="meta-cell">
                <div class="meta-label">Véhicule</div>
                <div class="meta-value">{{ $busRegistration }}</div>
            </div>
            <div class="meta-cell"></div>
        </div>
    </div>

    <table class="passengers">
        <thead>
            <tr>
                <th class="num">#</th>
                <th>Nom</th>
                @if ($international)
                    <th>Nationalité</th>
                    <th>Document</th>
                    <th>Naissance</th>
                @endif
                <th>Destination</th>
                <th>Siège</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($passengers as $passenger)
                <tr>
                    <td class="num">{{ $passenger['index'] }}</td>
                    <td>{{ $passenger['name'] }}</td>
                    @if ($international)
                        <td>{{ $passenger['nationality'] }}</td>
                        <td>
                            @if ($passenger['documentLabel'] !== '')
                                {{ $passenger['documentLabel'] }}<br>
                            @endif
                            {{ $passenger['documentNumber'] }}
                        </td>
                        <td>{{ $passenger['birthDate'] }}</td>
                    @endif
                    <td>{{ $passenger['destination'] }}</td>
                    <td>{{ $passenger['seat'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Version du manifeste : {{ $manifestVersion }} — Généré le {{ $generatedAtLabel }}
    </div>
</body>

</html>
