import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../test_utils.dart';

void main() {
  group('PassengerTile', () {
    testWidgets('affiche le nom, le sous-titre et l\'initiale en avatar', (tester) async {
      await pumpKoraWidget(
        tester,
        const PassengerTile(name: 'Awa Kouassi', subtitle: 'Siège 12A'),
      );

      expect(find.text('Awa Kouassi'), findsOneWidget);
      expect(find.text('Siège 12A'), findsOneWidget);
      expect(find.text('A'), findsOneWidget);
    });

    testWidgets('affiche le badge de statut quand fourni', (tester) async {
      await pumpKoraWidget(
        tester,
        const PassengerTile(
          name: 'Awa Kouassi',
          statusLabel: 'Embarqué',
          statusVariant: KoraTagVariant.gold,
        ),
      );

      expect(find.text('EMBARQUÉ'), findsOneWidget);
    });

    testWidgets('aucun badge quand statusLabel est null', (tester) async {
      await pumpKoraWidget(tester, const PassengerTile(name: 'Awa Kouassi'));

      expect(find.byType(KoraTag), findsNothing);
    });

    testWidgets('déclenche onTap au tap', (tester) async {
      var tapped = false;
      await pumpKoraWidget(
        tester,
        PassengerTile(name: 'Awa Kouassi', onTap: () => tapped = true),
      );

      await tester.tap(find.byType(PassengerTile));
      await tester.pump();

      expect(tapped, isTrue);
    });
  });
}
