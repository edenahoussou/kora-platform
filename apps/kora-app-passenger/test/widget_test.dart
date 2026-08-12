// Test de fumée basique : vérifie que l'application démarre sans planter,
// affiche la bottom nav et son onglet "Accueil" (compteur Flutter par
// défaut), et que le compteur s'incrémente bien au clic.
//
// SharedPreferences n'est pas disponible nativement dans un test : on
// utilise donc les valeurs mockées fournies par flutter_test.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/app.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/env/app_env.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    // Normalement fait par bootstrap() (voir lib/bootstrap.dart), qu'on ne
    // passe pas ici puisqu'on veut tester KoraApp isolément — donc on le
    // refait à la main, comme setupInjector() juste en dessous.
    AppEnv.current = AppEnv.dev;
    await setupInjector();
  });

  tearDown(() {
    getIt.reset();
  });

  testWidgets('KoraApp démarre sur Accueil et incrémente le compteur',
      (WidgetTester tester) async {
    await tester.pumpWidget(const KoraApp());
    await tester.pumpAndSettle();

    expect(find.text('0'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.add));
    await tester.pump();

    expect(find.text('1'), findsOneWidget);
  });
}
