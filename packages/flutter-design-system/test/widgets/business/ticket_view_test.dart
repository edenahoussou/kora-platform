import 'package:flutter/widgets.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../test_utils.dart';

void main() {
  group('TicketView', () {
    testWidgets('affiche toutes les informations du ticket', (tester) async {
      await pumpKoraWidget(
        tester,
        const TicketView(
          passengerName: 'A. HOUNKPÈ',
          originCode: 'CTN',
          origin: 'Cotonou',
          destinationCode: 'MLV',
          destination: 'Malanville',
          date: '04 AOÛ',
          departureTime: '07:30',
          seatLabel: '4A',
          ticketCode: 'KE-20260804-78342',
        ),
      );

      expect(find.text('A. HOUNKPÈ'), findsOneWidget);
      expect(find.text('CTN'), findsOneWidget);
      expect(find.text('Cotonou'), findsOneWidget);
      expect(find.text('MLV'), findsOneWidget);
      expect(find.text('Malanville'), findsOneWidget);
      expect(find.text('04 AOÛ'), findsOneWidget);
      expect(find.text('07:30'), findsOneWidget);
      expect(find.text('4A'), findsOneWidget);
      expect(find.text('KE-20260804-78342'), findsOneWidget);
      // Le label passe par KoraTag, qui le met en majuscules.
      expect(find.text('CONFIRMÉ'), findsOneWidget);

      // Vérifie que le bon asset logo est câblé, sans dépendre du
      // décodage réel de l'image (voir la note dans
      // ticket_view_golden_test.dart : le décodage raster ne se termine
      // pas dans l'horloge simulée de ce harnais de test).
      final logo = tester.widget<Image>(find.byType(Image));
      expect((logo.image as AssetImage).assetName, 'assets/brand/kora-logo.png');
      expect((logo.image as AssetImage).package, 'flutter_design_system');
    });

    testWidgets('billet annulé : affiche le tampon ANNULÉ', (tester) async {
      await pumpKoraWidget(
        tester,
        const TicketView(
          passengerName: 'A. HOUNKPÈ',
          originCode: 'CTN',
          origin: 'Cotonou',
          destinationCode: 'MLV',
          destination: 'Malanville',
          date: '04 AOÛ',
          departureTime: '07:30',
          seatLabel: '4A',
          ticketCode: 'KE-20260804-78342',
          isCancelled: true,
        ),
      );

      // Une fois dans le tag de statut (KoraTag met en majuscules), une
      // fois dans le tampon superposé.
      expect(find.text('ANNULÉ'), findsNWidgets(2));
    });
  });
}
