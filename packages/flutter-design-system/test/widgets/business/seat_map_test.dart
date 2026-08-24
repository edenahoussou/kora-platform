import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../test_utils.dart';

void main() {
  group('SeatMap', () {
    const seats = [
      KoraSeat(label: '1A'),
      KoraSeat(label: '1B', status: KoraSeatStatus.paid),
      KoraSeat(label: '1C', status: KoraSeatStatus.selected),
      KoraSeat(label: '1D', status: KoraSeatStatus.blocked),
      KoraSeat(label: '1E', status: KoraSeatStatus.embarked),
      KoraSeat(label: '1F', status: KoraSeatStatus.held),
    ];

    testWidgets('affiche le label de chaque siège', (tester) async {
      await pumpKoraWidget(tester, const SeatMap(seats: seats));

      for (final seat in seats) {
        expect(find.text(seat.label), findsOneWidget);
      }
    });

    testWidgets(
      'affiche le minuteur à la place du label quand held + timerLabel',
      (tester) async {
        await pumpKoraWidget(
          tester,
          const SeatMap(
            seats: [
              KoraSeat(
                label: '1F',
                status: KoraSeatStatus.held,
                timerLabel: '06:18',
              ),
            ],
          ),
        );

        expect(find.text('06:18'), findsOneWidget);
        expect(find.text('1F'), findsNothing);
      },
    );

    testWidgets('tap sur un siège disponible déclenche onSeatTap', (
      tester,
    ) async {
      KoraSeat? tapped;
      await pumpKoraWidget(
        tester,
        SeatMap(seats: seats, onSeatTap: (seat) => tapped = seat),
      );

      await tester.tap(find.text('1A'));
      await tester.pump();

      expect(tapped?.label, '1A');
    });

    testWidgets('tap sur un siège payé ne déclenche rien', (tester) async {
      KoraSeat? tapped;
      await pumpKoraWidget(
        tester,
        SeatMap(seats: seats, onSeatTap: (seat) => tapped = seat),
      );

      await tester.tap(find.text('1B'));
      await tester.pump();

      expect(tapped, isNull);
    });

    testWidgets('tap sur un siège bloqué ne déclenche rien', (tester) async {
      KoraSeat? tapped;
      await pumpKoraWidget(
        tester,
        SeatMap(seats: seats, onSeatTap: (seat) => tapped = seat),
      );

      await tester.tap(find.text('1D'));
      await tester.pump();

      expect(tapped, isNull);
    });

    testWidgets('tap sur un siège embarqué ou réservé ne déclenche rien', (
      tester,
    ) async {
      KoraSeat? tapped;
      await pumpKoraWidget(
        tester,
        SeatMap(seats: seats, onSeatTap: (seat) => tapped = seat),
      );

      await tester.tap(find.text('1E'));
      await tester.pump();
      expect(tapped, isNull);

      await tester.tap(find.text('1F'));
      await tester.pump();
      expect(tapped, isNull);
    });

    testWidgets(
      'tap sur un siège déjà sélectionné redéclenche onSeatTap (toggle géré par l\'appelant)',
      (tester) async {
        KoraSeat? tapped;
        await pumpKoraWidget(
          tester,
          SeatMap(seats: seats, onSeatTap: (seat) => tapped = seat),
        );

        await tester.tap(find.text('1C'));
        await tester.pump();

        expect(tapped?.label, '1C');
      },
    );

    group('palette et légende', () {
      testWidgets('une palette remplace la couleur des sièges', (tester) async {
        await pumpKoraWidget(
          tester,
          const SeatMap(
            seats: seats,
            showLegend: false,
            palette: {KoraSeatStatus.available: Color(0xFF0057B8)},
          ),
        );

        // Le passager suit la légende de son ticket — libre en bleu —
        // là où la maquette, faite pour l'équipage, laisse le siège blanc.
        final material = tester.widget<Material>(
          find
              .ancestor(of: find.text('1A'), matching: find.byType(Material))
              .first,
        );
        expect(material.color, const Color(0xFF0057B8));
      });

      testWidgets('un siège recoloré garde sa forme barrée', (tester) async {
        await pumpKoraWidget(
          tester,
          const SeatMap(
            seats: seats,
            showLegend: false,
            palette: {KoraSeatStatus.blocked: Color(0xFFF6B000)},
          ),
        );

        // La couleur change, la barre reste : c'est elle qui dit
        // « indisponible » à qui ne distingue pas les teintes.
        expect(find.byType(SeatMap), findsOneWidget);
        final text = tester.widget<Text>(find.text('1D'));
        expect(text.style?.decoration, TextDecoration.lineThrough);
      });

      testWidgets('la légende se limite aux états demandés', (tester) async {
        await pumpKoraWidget(
          tester,
          const SeatMap(
            seats: seats,
            legendStatuses: [KoraSeatStatus.available, KoraSeatStatus.selected],
          ),
        );

        expect(find.text('Libre'), findsOneWidget);
        expect(find.text('Votre choix'), findsOneWidget);
        // Les états d'embarquement ne concernent pas le voyageur.
        expect(find.text('Embarqué'), findsNothing);
        expect(find.text('Payé'), findsNothing);
      });

      testWidgets('les six états sont montrés par défaut', (tester) async {
        await pumpKoraWidget(tester, const SeatMap(seats: seats));

        for (final label in [
          'Libre',
          'Votre choix',
          'Réservé',
          'Payé',
          'Embarqué',
          'Bloqué',
        ]) {
          expect(find.text(label), findsOneWidget, reason: 'état $label');
        }
      });
    });
  });
}
