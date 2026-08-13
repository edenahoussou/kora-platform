import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../test_utils.dart';

void main() {
  group('DepartureCard', () {
    testWidgets('affiche heures, trajet, durée, prix et places disponibles', (
      tester,
    ) async {
      await pumpKoraWidget(
        tester,
        const DepartureCard(
          departureTime: '07:30',
          arrivalTime: '17:15',
          origin: 'Cotonou',
          destination: 'Malanville',
          duration: '≈ 9 h 45',
          price: '12 500 FCFA',
          seatsAvailable: 12,
        ),
      );

      expect(find.text('07:30'), findsOneWidget);
      expect(find.text('17:15'), findsOneWidget);
      expect(find.text('Cotonou'), findsOneWidget);
      expect(find.text('Malanville'), findsOneWidget);
      expect(find.text('≈ 9 h 45'), findsOneWidget);
      expect(find.text('12 500 FCFA'), findsOneWidget);
      expect(find.text('12 places'), findsOneWidget);
      // Le label passe par KoraTag, qui le met en majuscules (`.tag`,
      // `text-transform: uppercase` — voir kora_tag.dart).
      expect(find.text('KORA CONFORT'), findsOneWidget);
    });

    testWidgets('masque les places quand seatsAvailable est null', (
      tester,
    ) async {
      await pumpKoraWidget(
        tester,
        const DepartureCard(
          departureTime: '09:15',
          arrivalTime: '09:52',
          origin: 'Abidjan',
          destination: 'Yamoussoukro',
          duration: '≈ 37 min',
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
          departureTime: '07:30',
          arrivalTime: '17:15',
          origin: 'Cotonou',
          destination: 'Malanville',
          duration: '≈ 9 h 45',
          price: '12 500 FCFA',
          onTap: () => tapped = true,
        ),
      );

      await tester.tap(find.byType(DepartureCard));
      await tester.pump();

      expect(tapped, isTrue);
    });

    testWidgets(
      'segment complet : affiche Complet/Indisponible et ignore le tap',
      (tester) async {
        var tapped = false;
        await pumpKoraWidget(
          tester,
          DepartureCard(
            departureTime: '22:30',
            arrivalTime: '08:15',
            origin: 'Cotonou',
            destination: 'Malanville',
            duration: '≈ 9 h 45',
            price: '12 500 FCFA',
            isSoldOut: true,
            onTap: () => tapped = true,
          ),
        );

        expect(find.text('COMPLET'), findsOneWidget);
        expect(find.text('Indisponible'), findsOneWidget);
        expect(find.textContaining('Prix final garanti'), findsNothing);

        await tester.tap(find.byType(DepartureCard));
        await tester.pump();

        expect(tapped, isFalse);
      },
    );
  });
}
