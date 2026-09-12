import 'dart:typed_data';

import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/group_ticket.dart';

/// Le billet, en PDF, tel qu'on l'emporte hors de l'app.
///
/// **Le document n'est pas ce qui fait foi : le QR l'est.** Le serveur
/// signe une charge Ed25519 que l'app de bord vérifie hors ligne, avec la
/// seule clé publique — `BuildTicketQrPayloadAction`. Ce PDF-ci se contente
/// de la porter, en grand, avec de quoi reconnaître le billet à l'œil. Il
/// vaut donc exactement le même embarquement que celui du serveur.
///
/// C'est aussi pourquoi on ne le fabrique que là où le QR **vient** du
/// serveur. Les écrans qui montrent encore un voyage de démonstration
/// replient sur la référence à défaut de charge signée, et un PDF bâti
/// dessus ne passerait aucun contrôle.
Future<Uint8List> buildTicketPdf({
  required GroupTicket ticket,
  required AppLocalization l10n,
  Departure? departure,
}) async {
  final document = pw.Document(title: 'KORA ${ticket.shortCode}');

  // Helvetica intégrée plutôt qu'une fonte téléchargée : on enregistre son
  // billet dans une gare en limite de couverture, et un appel réseau au
  // moment de l'export choisirait mal son moment.
  final theme = pw.ThemeData.withFont();

  document.addPage(
    pw.Page(
      pageFormat: PdfPageFormat.a5,
      theme: theme,
      margin: const pw.EdgeInsets.all(22),
      build: (context) => pw.Column(
        crossAxisAlignment: pw.CrossAxisAlignment.stretch,
        children: [
          _header(l10n),
          pw.SizedBox(height: 18),
          if (departure != null) ...[
            _route(departure, l10n),
            pw.SizedBox(height: 16),
          ],
          _identity(ticket, l10n),
          pw.SizedBox(height: 18),
          pw.Center(
            child: pw.BarcodeWidget(
              // La charge du serveur, jamais la référence : c'est elle que
              // la douchette du convoyeur attend.
              data: ticket.qrCode,
              barcode: pw.Barcode.qrCode(),
              width: 185,
              height: 185,
              drawText: false,
            ),
          ),
          pw.SizedBox(height: 10),
          pw.Center(
            child: pw.Text(
              ticket.shortCode,
              style: pw.TextStyle(
                fontSize: 13,
                fontWeight: pw.FontWeight.bold,
                letterSpacing: 1.5,
              ),
            ),
          ),
          pw.Spacer(),
          pw.Text(
            l10n.ticketsOneNotice,
            style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
          ),
        ],
      ),
    ),
  );

  return document.save();
}

/// Le bandeau navy et son trait doré — la marque, en sobre.
pw.Widget _header(AppLocalization l10n) {
  return pw.Container(
    padding: const pw.EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    color: _kora,
    child: pw.Row(
      mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
      crossAxisAlignment: pw.CrossAxisAlignment.end,
      children: [
        pw.Column(
          crossAxisAlignment: pw.CrossAxisAlignment.start,
          children: [
            pw.Text(
              'KORA EXPRESS',
              style: pw.TextStyle(
                color: PdfColors.white,
                fontSize: 15,
                fontWeight: pw.FontWeight.bold,
                letterSpacing: 1.2,
              ),
            ),
            pw.SizedBox(height: 3),
            pw.Text(
              l10n.ticketEyebrow.toUpperCase(),
              style: const pw.TextStyle(
                color: _gold,
                fontSize: 8,
                letterSpacing: 0.8,
              ),
            ),
          ],
        ),
        pw.Container(width: 46, height: 4, color: _gold),
      ],
    ),
  );
}

/// Le trajet, quand l'écran d'où part l'export le connaît.
pw.Widget _route(Departure departure, AppLocalization l10n) {
  final line = departure.line;

  return pw.Column(
    crossAxisAlignment: pw.CrossAxisAlignment.start,
    children: [
      // Flèche ASCII : les fontes PDF intégrées sont en WinAnsi, où le
      // « → » n'existe pas — il sortirait en carré vide.
      pw.Text(
        '${line.origin.name} -> ${line.destination.name}',
        style: pw.TextStyle(fontSize: 19, fontWeight: pw.FontWeight.bold),
      ),
      pw.SizedBox(height: 4),
      pw.Text(
        '${KoraDateFormats.weekdayDayMonthIn(departure.departsAt, l10n.languageCode)}'
        '  -  ${KoraDateFormats.time.format(departure.departsAt)}',
        style: const pw.TextStyle(fontSize: 11, color: PdfColors.grey700),
      ),
    ],
  );
}

/// Le voyageur et son siège, côte à côte.
pw.Widget _identity(GroupTicket ticket, AppLocalization l10n) {
  return pw.Row(
    crossAxisAlignment: pw.CrossAxisAlignment.start,
    children: [
      pw.Expanded(
        child: _field(
          label: l10n.ticketFieldPassenger.toUpperCase(),
          value: ticket.label,
        ),
      ),
      if (ticket.seatCode.isNotEmpty)
        _field(
          label: l10n.ticketFieldSeat.toUpperCase(),
          value: ticket.seatCode,
          alignEnd: true,
        ),
    ],
  );
}

pw.Widget _field({
  required String label,
  required String value,
  bool alignEnd = false,
}) {
  return pw.Column(
    crossAxisAlignment: alignEnd
        ? pw.CrossAxisAlignment.end
        : pw.CrossAxisAlignment.start,
    children: [
      pw.Text(
        label,
        style: const pw.TextStyle(
          fontSize: 7,
          color: PdfColors.grey600,
          letterSpacing: 0.8,
        ),
      ),
      pw.SizedBox(height: 3),
      pw.Text(
        value,
        style: pw.TextStyle(fontSize: 13, fontWeight: pw.FontWeight.bold),
      ),
    ],
  );
}

/// `--kora: #001D4E` et `--gold: #F6B000` du design system.
const PdfColor _kora = PdfColor.fromInt(0xFF001D4E);
const PdfColor _gold = PdfColor.fromInt(0xFFF6B000);
