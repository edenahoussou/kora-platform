import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

/// Tests golden : capturent le rendu pixel de [KoraButton] pour détecter
/// toute régression visuelle involontaire (couleur, rayon, ombre...).
/// Régénérer les masters après un changement volontaire de design :
/// `flutter test --update-goldens test/golden`
void main() {
  group('KoraButton — golden', () {
    testWidgets('primary', (tester) async {
      await pumpKoraWidget(
        tester,
        KoraButton(label: 'Continuer', onPressed: () {}),
        surfaceSize: const Size(300, 100),
      );
      await expectLater(find.byType(KoraButton), matchesGoldenFile('kora_button_primary.png'));
    });

    testWidgets('secondary', (tester) async {
      await pumpKoraWidget(
        tester,
        KoraButton(label: 'Voir les détails', variant: KoraButtonVariant.secondary, onPressed: () {}),
        surfaceSize: const Size(300, 100),
      );
      await expectLater(find.byType(KoraButton), matchesGoldenFile('kora_button_secondary.png'));
    });

    testWidgets('ghost', (tester) async {
      await pumpKoraWidget(
        tester,
        KoraButton(label: 'Annuler', variant: KoraButtonVariant.ghost, onPressed: () {}),
        surfaceSize: const Size(300, 100),
      );
      await expectLater(find.byType(KoraButton), matchesGoldenFile('kora_button_ghost.png'));
    });

    testWidgets('disabled', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraButton(label: 'Continuer', onPressed: null),
        surfaceSize: const Size(300, 100),
      );
      await expectLater(find.byType(KoraButton), matchesGoldenFile('kora_button_disabled.png'));
    });

    testWidgets('dark theme', (tester) async {
      await pumpKoraWidget(
        tester,
        KoraButton(label: 'Continuer', onPressed: () {}),
        surfaceSize: const Size(300, 100),
        dark: true,
      );
      await expectLater(find.byType(KoraButton), matchesGoldenFile('kora_button_dark.png'));
    });
  });
}
