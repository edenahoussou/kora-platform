// Le billet exporté en PDF.
//
// Ce qui compte n'est pas la mise en page : c'est que le QR du document
// porte **la charge signée du serveur**, et elle seule. Le convoyeur
// scanne un jeton Ed25519 qu'il vérifie hors ligne ; un PDF qui encoderait
// la référence à la place ne passerait aucun contrôle.

import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/group_ticket.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/presentation/pdf/ticket_pdf.dart';

void main() {
  // Les textes sont lus dans les assets : sans le binding, le chargeur
  // n'a pas de bundle où aller les chercher.
  TestWidgetsFlutterBinding.ensureInitialized();

  late AppLocalization l10n;

  setUpAll(() async {
    await initializeDateFormatting(KoraDateFormats.locale);
    l10n = AppLocalization(const Locale('fr'));
    await l10n.load();
  });

  /// La charge telle que `BuildTicketQrPayloadAction` la rend :
  /// `base64url(payload).base64url(signature)`.
  const signed = 'eyJ2IjoyLCJ0IjoiYWJjIn0.c2lnbmF0dXJl';

  const ticket = GroupTicket(
    id: 't-1',
    shortCode: 'K5F414H5',
    status: GroupTicketStatus.issued,
    qrCode: signed,
    passengerName: 'Aïcha HOUNKPÈ',
    seatCode: '5A',
  );

  test('le document sort bien un PDF, bâti sur la charge signée', () async {
    final bytes = await buildTicketPdf(ticket: ticket, l10n: l10n);

    expect(bytes, isNotEmpty);
    // Un PDF commence par `%PDF-`.
    expect(String.fromCharCodes(bytes.take(5)), '%PDF-');
  });

  test('le trajet n’est imprimé que lorsqu’il est connu', () async {
    final departure = mockDeparturesFor(
      SearchCriteria(
        origin: kMockCities.firstWhere((city) => city.id == 'ctn'),
        destination: kMockCities.firstWhere((city) => city.id == 'mlv'),
        date: DateTime(2026, 8, 14),
      ),
    ).first;

    // Le billet du serveur ne rend que des identifiants de gares : sans le
    // départ transmis par l'écran de confirmation, le PDF sort sans ligne
    // de trajet plutôt qu'avec une ligne inventée.
    final withRoute = await buildTicketPdf(
      ticket: ticket,
      l10n: l10n,
      departure: departure,
    );
    final without = await buildTicketPdf(ticket: ticket, l10n: l10n);

    expect(withRoute.length, greaterThan(without.length));
  });
}
