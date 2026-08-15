// Test de fumée : vérifie que l'application démarre sur l'écran de
// bienvenue, puis qu'entrer dans l'app affiche l'accueil et sa bottom nav
// avec ses 5 onglets.
//
// SharedPreferences n'est pas disponible nativement dans un test : on
// utilise donc les valeurs mockées fournies par flutter_test.

import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/app.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/env/app_env.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUp(() async {
    SharedPreferences.setMockInitialValues({});

    // `AuthGuard` (le `redirect` du routeur) lit le stockage sécurisé dès
    // qu'on navigue vers /login. Son plugin natif n'existe pas dans un
    // test : sans ce faux canal, la navigation échouerait ici alors
    // qu'elle fonctionne sur l'appareil.
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
          (call) async => null,
        );
    // Normalement fait par bootstrap() (voir lib/bootstrap.dart), qu'on ne
    // passe pas ici puisqu'on veut tester KoraApp isolément — donc on le
    // refait à la main, comme setupInjector() juste en dessous.
    AppEnv.current = AppEnv.dev;
    await initializeDateFormatting(KoraDateFormats.locale);
    await setupInjector();
  });

  tearDown(() {
    getIt.reset();
  });

  testWidgets('KoraApp démarre sur la bienvenue, puis ouvre l’accueil', (
    WidgetTester tester,
  ) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(const KoraApp());
    // Pas de `pumpAndSettle` : le bus de l'écran de bienvenue traverse la
    // route en boucle, l'arbre ne se stabilise jamais.
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('Créer mon compte'), findsOneWidget);

    // Entrer dans l'app passe par la connexion : PIN à 4 chiffres puis
    // validation. Aucun code n'est vérifié tant que la feature `auth`
    // n'existe pas (voir LoginPage).
    await tester.tap(find.text('J’ai déjà un compte'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 600));

    expect(find.text('Votre PIN KORA'), findsOneWidget);
    for (final key in ['1', '2', '3', '4']) {
      await tester.tap(find.text(key));
      await tester.pump();
    }
    await tester.tap(find.text('✓'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 600));

    // L'écran d'accueil (recherche de départs Bus) est affiché.
    expect(find.text('Où souhaitez-vous voyager ?'), findsOneWidget);
    expect(find.text('Rechercher un voyage'), findsOneWidget);

    // La bottom nav propose bien ses 5 onglets. On cible les libellés
    // DANS la barre : "Colis" et "Wallet" apparaissent aussi dans les
    // accès rapides de l'accueil.
    for (final label in ['Accueil', 'Voyages', 'Colis', 'Wallet', 'Profil']) {
      expect(
        find.descendant(
          of: find.byType(KoraBottomNav),
          matching: find.text(label),
        ),
        findsOneWidget,
        reason: 'onglet "$label" absent de la bottom nav',
      );
    }
  });
}
