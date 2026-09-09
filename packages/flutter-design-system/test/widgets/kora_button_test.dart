import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('KoraButton', () {
    testWidgets('affiche son label et déclenche onPressed au tap', (
      tester,
    ) async {
      var tapped = false;
      await pumpKoraWidget(
        tester,
        KoraButton(label: 'Continuer', onPressed: () => tapped = true),
      );

      expect(find.text('Continuer'), findsOneWidget);

      await tester.tap(find.byType(KoraButton));
      await tester.pump();

      expect(tapped, isTrue);
    });

    testWidgets('désactivé quand onPressed est null', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraButton(label: 'Continuer', onPressed: null),
      );

      final button = tester.widget<ElevatedButton>(find.byType(ElevatedButton));
      expect(button.onPressed, isNull);
    });

    testWidgets('état loading affiche un spinner et bloque le tap', (
      tester,
    ) async {
      var tapped = false;
      await pumpKoraWidget(
        tester,
        KoraButton(
          label: 'Continuer',
          isLoading: true,
          onPressed: () => tapped = true,
        ),
      );

      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(find.text('Continuer'), findsNothing);

      await tester.tap(find.byType(KoraButton));
      await tester.pump();

      expect(tapped, isFalse);
    });

    testWidgets('affiche l\'icône fournie', (tester) async {
      await pumpKoraWidget(
        tester,
        KoraButton(label: 'Payer', icon: Icons.lock_outline, onPressed: () {}),
      );

      expect(find.byIcon(Icons.lock_outline), findsOneWidget);
    });

    /// Le contour effectivement peint par le bouton, ou `null`.
    BorderSide? sideOf(WidgetTester tester) {
      final button = tester.widget<ElevatedButton>(find.byType(ElevatedButton));
      return button.style?.side?.resolve(const <WidgetState>{});
    }

    testWidgets('le bouton secondaire prend un filet en thème sombre', (
      tester,
    ) async {
      // Le fond d'écran sombre EST le navy du bouton : sans filet il perd
      // ses bords, et il ne reste que son libellé flottant. C'est le
      // traitement que `KoraCard.navy` s'applique déjà.
      await pumpKoraWidget(
        tester,
        KoraButton(
          label: 'Voir les détails',
          variant: KoraButtonVariant.secondary,
          onPressed: () {},
        ),
        dark: true,
      );

      expect(sideOf(tester)?.color, isNotNull);
      expect(sideOf(tester)!.color.a, greaterThan(0));
    });

    testWidgets('en thème clair il garde son aplat sans contour', (
      tester,
    ) async {
      // Le navy y tranche déjà sur la page : un filet alourdirait le
      // bouton pour rien.
      await pumpKoraWidget(
        tester,
        KoraButton(
          label: 'Voir les détails',
          variant: KoraButtonVariant.secondary,
          onPressed: () {},
        ),
      );

      expect(sideOf(tester), isNull);
    });

    for (final variant in KoraButtonVariant.values) {
      testWidgets('variante $variant se construit sans erreur', (tester) async {
        await pumpKoraWidget(
          tester,
          KoraButton(label: 'Label', variant: variant, onPressed: () {}),
        );
        expect(find.byType(KoraButton), findsOneWidget);
      });
    }
  });
}
