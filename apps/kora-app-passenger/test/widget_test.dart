// Test de fumée : vérifie que l'application démarre sans planter,
// que la bottom nav est présente avec ses 5 onglets, et que l'onglet
// "Accueil" est actif par défaut.
//
// SharedPreferences n'est pas disponible nativement dans un test : on
// utilise donc les valeurs mockées fournies par flutter_test.

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

  testWidgets('KoraApp démarre sur Accueil avec la bottom nav', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const KoraApp());
    await tester.pumpAndSettle();

    // L'AppBar de l'onglet Accueil est visible.
    expect(find.text('Accueil'), findsNWidgets(2));

    // La bottom nav propose bien 5 onglets (Accueil, Voyages, Colis,
    // Wallet, Profil) — on vérifie leur présence via les labels.
    expect(find.text('Voyages'), findsOneWidget);
    expect(find.text('Colis'), findsOneWidget);
    expect(find.text('Wallet'), findsOneWidget);
    expect(find.text('Profil'), findsOneWidget);
  });
}
