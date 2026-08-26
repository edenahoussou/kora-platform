// L'en-tête de l'accueil, et surtout sa cloche.
//
// En thème sombre, un cadre décoratif est posé par-dessus tout l'en-tête
// pour en redessiner l'arrondi. Sans `IgnorePointer`, il interceptait le
// toucher et le bouton de notifications ne répondait plus — dans ce thème
// seulement, ce qui rendait la panne difficile à voir.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/home/presentation/widgets/home_hero.dart';

void main() {
  Future<void> pumpHero(
    WidgetTester tester, {
    required ThemeData theme,
    required VoidCallback onTap,
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: theme,
        home: Scaffold(
          body: SingleChildScrollView(
            child: HomeHero(
              greeting: 'Bonjour',
              title: 'Votre prochain voyage',
              onNotificationsTap: onTap,
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  for (final (name, theme) in [
    ('clair', AppTheme.light),
    ('sombre', AppTheme.dark),
  ]) {
    testWidgets('la cloche répond en thème $name', (tester) async {
      var taps = 0;
      await pumpHero(tester, theme: theme, onTap: () => taps++);

      await tester.tap(find.byIcon(Icons.notifications_none_rounded));
      await tester.pumpAndSettle();

      expect(taps, 1, reason: 'thème $name');
    });
  }
}
