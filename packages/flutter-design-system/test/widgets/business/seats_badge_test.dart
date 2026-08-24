import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../test_utils.dart';

void main() {
  group('KoraSeatsLevel', () {
    test('vert tant qu’au moins 30 % du bus est libre', () {
      expect(
        KoraSeatsLevel.of(available: 55, total: 55),
        KoraSeatsLevel.plenty,
      );
      // Pile sur le seuil : 30 % est encore « vert ».
      expect(
        KoraSeatsLevel.of(available: 17, total: 55),
        KoraSeatsLevel.plenty,
      );
    });

    test('orange entre 10 % et 30 %', () {
      // 12/55 = 21,8 %.
      expect(
        KoraSeatsLevel.of(available: 12, total: 55),
        KoraSeatsLevel.limited,
      );
      // Juste sous le seuil du vert.
      expect(
        KoraSeatsLevel.of(available: 16, total: 55),
        KoraSeatsLevel.limited,
      );
    });

    test('rouge en dessous de 10 %', () {
      // 5/55 = 9,1 %.
      expect(KoraSeatsLevel.of(available: 5, total: 55), KoraSeatsLevel.last);
      expect(KoraSeatsLevel.of(available: 1, total: 55), KoraSeatsLevel.last);
    });

    test('sans capacité connue, aucune urgence n’est inventée', () {
      // L'API ne renvoie pas toujours le véhicule : sans dénominateur, il
      // n'y a aucun remplissage à qualifier.
      expect(KoraSeatsLevel.of(available: 3, total: 0), KoraSeatsLevel.plenty);
      expect(KoraSeatsLevel.of(available: 3, total: -1), KoraSeatsLevel.plenty);
    });
  });

  group('KoraSeatsBadge', () {
    testWidgets('affiche le nombre de places, accordé en nombre', (
      tester,
    ) async {
      await pumpKoraWidget(
        tester,
        const KoraSeatsBadge(available: 1, total: 55),
      );
      // Le label passe par KoraTag, qui le met en majuscules.
      expect(find.text('1 PLACE'), findsOneWidget);

      await pumpKoraWidget(
        tester,
        const KoraSeatsBadge(available: 12, total: 55),
      );
      expect(find.text('12 PLACES'), findsOneWidget);
    });

    testWidgets('porte la variante de couleur de son niveau', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraSeatsBadge(available: 22, total: 55),
      );

      expect(
        tester.widget<KoraTag>(find.byType(KoraTag)).variant,
        KoraTagVariant.success,
      );
    });
  });

  group('DepartureCard', () {
    testWidgets('colore le badge des places d’après la capacité du bus', (
      tester,
    ) async {
      // Mêmes 12 places libres, deux bus de tailles différentes : sur 55
      // places c'est un bus qui se remplit, sur 20 il reste tranquille.
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
          seatsTotal: 55,
        ),
      );
      expect(tester.widget<KoraTag>(_seatsTag).variant, KoraTagVariant.gold);

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
          seatsTotal: 20,
        ),
      );
      expect(tester.widget<KoraTag>(_seatsTag).variant, KoraTagVariant.success);
    });

    testWidgets('sans capacité connue, le badge reste neutre', (tester) async {
      await pumpKoraWidget(
        tester,
        const DepartureCard(
          departureTime: '07:30',
          arrivalTime: '17:15',
          origin: 'Cotonou',
          destination: 'Malanville',
          duration: '≈ 9 h 45',
          price: '12 500 FCFA',
          seatsAvailable: 3,
        ),
      );

      expect(find.text('3 PLACES'), findsOneWidget);
      expect(tester.widget<KoraTag>(_seatsTag).variant, KoraTagVariant.success);
    });
  });
}

/// Le [KoraTag] du badge des places — la carte en contient un second (le
/// badge doré du véhicule), qu'il faut écarter.
final Finder _seatsTag = find.descendant(
  of: find.byType(KoraSeatsBadge),
  matching: find.byType(KoraTag),
);
