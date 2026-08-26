import 'package:flutter/material.dart';
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
      expect(find.text('1 place'), findsOneWidget);

      await pumpKoraWidget(
        tester,
        const KoraSeatsBadge(available: 12, total: 55),
      );
      expect(find.text('12 places'), findsOneWidget);
    });

    testWidgets('reste du texte, sans pilule autour', (tester) async {
      // La carte porte déjà une pilule pour le véhicule : une seconde
      // mettrait sur le même plan ce qu'on choisit et ce qui reste.
      await pumpKoraWidget(
        tester,
        const KoraSeatsBadge(available: 22, total: 55),
      );

      expect(find.byType(KoraTag), findsNothing);
    });

    testWidgets('porte la couleur de son niveau', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraSeatsBadge(available: 5, total: 55),
      );
      expect(_colorOf(tester, '5 places'), AppColors.danger);

      await pumpKoraWidget(
        tester,
        const KoraSeatsBadge(available: 12, total: 55),
      );
      expect(_colorOf(tester, '12 places'), AppColors.gold);
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
      expect(_colorOf(tester, '12 places'), AppColors.gold);

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
      // Rien ne presse : la ligne reste dans le gris du reste de la carte.
      expect(_colorOf(tester, '12 places'), isNot(AppColors.gold));
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

      expect(find.text('3 places'), findsOneWidget);
      // Sans dénominateur, aucune urgence n'est inventée.
      expect(_colorOf(tester, '3 places'), isNot(AppColors.danger));
    });
  });
}

/// La couleur du texte des places.
Color? _colorOf(WidgetTester tester, String label) =>
    tester.widget<Text>(find.text(label)).style?.color;
