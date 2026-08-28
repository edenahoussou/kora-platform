// Test de fumée : l'app démarre là où la session le commande.
//
// Sans session, elle s'arrête sur la bienvenue et le garde de navigation
// interdit les écrans protégés. Avec une session en stockage sécurisé,
// elle ouvre directement l'accueil et sa bottom nav.
//
// Ni SharedPreferences ni le stockage sécurisé n'ont d'implémentation
// native dans un test : leurs canaux sont donc simulés ici.

import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/app.dart';
import 'package:kora_app_passenger/core/constants/storage_keys.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/env/app_env.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_data_source.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  /// Contenu simulé du stockage sécurisé, relu par le canal ci-dessous.
  late Map<String, String> secureStorage;

  setUpAll(() async {
    // `AppEnv.current` est un `late final` : l'assigner à chaque test
    // lèverait « already initialized » dès le second.
    AppEnv.current = AppEnv.dev;
    await initializeDateFormatting(KoraDateFormats.locale);
    // Comme bootstrap() : sans les textes déjà lus, `Localizations`
    // n'afficherait rien pendant les premières frames et le test ne
    // trouverait aucun libellé.
    await AppLocalization.preloadAll();
  });

  /// Reconstruit l'injecteur sur un stockage local donné.
  ///
  /// Les préférences doivent être posées **avant** : `setupInjector` retient
  /// l'instance de `SharedPreferences`, et la changer ensuite n'aurait plus
  /// d'effet. C'est ce qui distingue une première ouverture d'une suivante.
  Future<void> boot({bool onboardingSeen = true}) async {
    await getIt.reset();
    SharedPreferences.setMockInitialValues(
      onboardingSeen ? {StorageKeys.onboardingSeen: true} : {},
    );

    // Normalement fait par bootstrap() (voir lib/bootstrap.dart), qu'on ne
    // passe pas ici puisqu'on veut tester KoraApp isolément.
    await setupInjector();

    // La recherche Bus tape sur l'API depuis le branchement du Sprint 2 ;
    // dans un test, aucune requête HTTP n'aboutit et l'accueil resterait
    // bloqué sur son écran d'erreur. On rebranche le jeu de
    // démonstration : ce test-ci vérifie la navigation, pas le réseau —
    // c'est `booking_remote_data_source_test.dart` qui couvre les appels.
    await getIt.unregister<BookingDataSource>();
    getIt.registerLazySingleton<BookingDataSource>(
      () => BookingMockDataSource(),
    );
  }

  setUp(() async {
    // Onboarding déjà vu : ces tests portent sur le garde de session, et
    // sans ce drapeau la première ouverture s'arrêterait sur les trois
    // temps de présentation. Un test dédié couvre ce cas plus bas.
    SharedPreferences.setMockInitialValues({StorageKeys.onboardingSeen: true});
    secureStorage = {};

    // `flutter_secure_storage` n'a pas de plugin natif en test : on
    // répond à sa place, à partir de `secureStorage`. C'est ce qui permet
    // de simuler « quelqu'un est déjà connecté ».
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
          (call) async {
            final arguments = Map<String, dynamic>.from(
              call.arguments as Map? ?? const {},
            );
            final key = arguments['key'] as String? ?? '';

            return switch (call.method) {
              'read' => secureStorage[key],
              'readAll' => secureStorage,
              'write' => secureStorage[key] = arguments['value'] as String,
              'delete' => secureStorage.remove(key),
              _ => null,
            };
          },
        );

    await boot();
  });

  tearDown(() {
    // Le routeur écoute `AuthBloc` : le laisser en place d'un test à
    // l'autre le ferait écouter un bloc fermé, et repartir de la position
    // du test précédent.
    AppRouter.resetForTesting();
    getIt.reset();
  });

  /// Écrit une session valide, comme le ferait une connexion réussie.
  void seedSession() {
    secureStorage[StorageKeys.accessToken] = 'token-de-test';
    secureStorage[StorageKeys.tokenExpiresAt] = DateTime.now()
        .add(const Duration(days: 7))
        .toIso8601String();
    secureStorage[StorageKeys.authUser] = jsonEncode({
      'uuid': 'user-1',
      'name': 'Aïcha HOUNKPÈ',
      'email': 'aicha@example.com',
      'permissions': <String>[],
    });
  }

  Future<void> pumpApp(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(const KoraApp());

    // Pas de `pumpAndSettle` : le bus de l'écran de bienvenue traverse la
    // route en boucle, et l'orbite du lancement tourne sans fin — l'arbre
    // ne se stabilise jamais. On pompe donc plusieurs fois, le temps que
    // le stockage sécurisé réponde et que le garde quitte le lancement.
    for (var i = 0; i < 6; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
  }

  testWidgets('sans session, KoraApp s’arrête sur la bienvenue', (
    tester,
  ) async {
    await pumpApp(tester);

    expect(
      find.text('Le réseau se connecte…'),
      findsNothing,
      reason: 'le lancement doit avoir rendu la main',
    );
    expect(find.text('Créer mon compte'), findsOneWidget);
    expect(find.text('J’ai déjà un compte'), findsOneWidget);

    // Le garde interdit l'accueil : l'app n'a pas de session.
    expect(find.text('Où souhaitez-vous voyager ?'), findsNothing);
  });

  testWidgets('avec une session valide, KoraApp ouvre l’accueil', (
    tester,
  ) async {
    seedSession();
    await pumpApp(tester);

    // La session est relue au démarrage : le garde redirige la bienvenue
    // vers l'accueil sans que l'utilisateur ait à se reconnecter.
    // Le temps laissé couvre la lecture du stockage, la redirection, puis
    // le chargement de l'accueil (la source mockée répond en 450 ms).
    for (var i = 0; i < 6; i++) {
      await tester.pump(const Duration(milliseconds: 300));
    }

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

  testWidgets('la connexion mène à la saisie du PIN', (tester) async {
    await pumpApp(tester);

    await tester.tap(find.text('J’ai déjà un compte'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 600));

    expect(find.text('Votre PIN KORA'), findsOneWidget);
  });

  testWidgets('à la toute première ouverture, l’onboarding passe avant', (
    tester,
  ) async {
    await boot(onboardingSeen: false);
    await pumpApp(tester);

    // Trois temps avant de demander un compte : on explique d'abord ce que
    // l'app fait. Le titre est un `Text.rich` — invisible à `find.text` —
    // donc on vise le corps du slide et sa navigation.
    expect(find.textContaining('Villes, date, voyageurs'), findsOneWidget);
    expect(find.text('Suivant'), findsOneWidget);
    expect(find.text('Créer mon compte'), findsNothing);
  });

  testWidgets('l’onboarding vu, il ne revient plus', (tester) async {
    await boot(onboardingSeen: false);
    await pumpApp(tester);

    await tester.tap(find.text('Passer'));
    // Pas de `pumpAndSettle` : l'écran de bienvenue qui suit anime son bus
    // en boucle, l'arbre ne se stabilise jamais.
    for (var i = 0; i < 6; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }

    expect(find.text('Créer mon compte'), findsOneWidget);

    // Le drapeau est écrit : une seconde ouverture va droit à la
    // bienvenue.
    await pumpApp(tester);
    expect(find.textContaining('Villes, date, voyageurs'), findsNothing);
  });
}
