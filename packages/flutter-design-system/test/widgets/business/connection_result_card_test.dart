import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../test_utils.dart';

void main() {
  group('ConnectionResultCard', () {
    testWidgets('affiche heures, trajet, note, segments et prix', (tester) async {
      await pumpKoraWidget(
        tester,
        const ConnectionResultCard(
          departureTime: '06:45',
          arrivalTime: '17:40',
          routeLabel: 'Cotonou → Bohicon → Natitingou',
          noteLabel: 'Changement de bus à Bohicon · attente 35 min',
          segmentsLabel: '2 segments inclus',
          price: '14 500 FCFA',
        ),
      );

      expect(find.text('06:45'), findsOneWidget);
      expect(find.text('17:40'), findsOneWidget);
      expect(find.text('Cotonou → Bohicon → Natitingou'), findsOneWidget);
      expect(find.text('Changement de bus à Bohicon · attente 35 min'), findsOneWidget);
      expect(find.text('2 segments inclus'), findsOneWidget);
      expect(find.text('14 500 FCFA ›'), findsOneWidget);
      // Le label passe par KoraTag, qui le met en majuscules.
      expect(find.text('1 CORRESPONDANCE'), findsOneWidget);
    });

    testWidgets('déclenche onTap au tap', (tester) async {
      var tapped = false;
      await pumpKoraWidget(
        tester,
        ConnectionResultCard(
          departureTime: '06:45',
          arrivalTime: '17:40',
          routeLabel: 'Cotonou → Bohicon → Natitingou',
          noteLabel: 'Changement de bus à Bohicon · attente 35 min',
          segmentsLabel: '2 segments inclus',
          price: '14 500 FCFA',
          onTap: () => tapped = true,
        ),
      );

      await tester.tap(find.byType(ConnectionResultCard));
      await tester.pump();

      expect(tapped, isTrue);
    });
  });
}
