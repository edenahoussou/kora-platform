import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../test_utils.dart';

void main() {
  group('DepartureCard', () {
    testWidgets('affiche heure, trajet, prix et places disponibles', (
      tester,
    ) async {
      await pumpKoraWidget(
        tester,
        const DepartureCard(
          departureTime: '14:30',
          origin: 'Cotonou',
          destination: 'Porto-Novo',
          price: '2 500 FCFA',
          seatsAvailable: 12,
        ),
      );

      expect(find.text('14:30'), findsOneWidget);
      expect(find.text('Cotonou'), findsOneWidget);
      expect(find.text('Porto-Novo'), findsOneWidget);
      expect(find.text('2 500 FCFA'), findsOneWidget);
      expect(find.text('12 places'), findsOneWidget);
    });

    testWidgets('masque les places quand seatsAvailable est null', (
      tester,
    ) async {
      await pumpKoraWidget(
        tester,
        const DepartureCard(
          departureTime: '09:15',
          origin: 'Abidjan',
          destination: 'Yamoussoukro',
          price: '8 000 FCFA',
        ),
      );

      expect(find.textContaining('places'), findsNothing);
    });

    testWidgets('déclenche onTap au tap', (tester) async {
      var tapped = false;
      await pumpKoraWidget(
        tester,
        DepartureCard(
          departureTime: '14:30',
          origin: 'Cotonou',
          destination: 'Porto-Novo',
          price: '2 500 FCFA',
          onTap: () => tapped = true,
        ),
      );

      await tester.tap(find.byType(DepartureCard));
      await tester.pump();

      expect(tapped, isTrue);
    });
  });
}
