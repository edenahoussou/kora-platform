// Connexion sur un appareil que le serveur ne connaît pas.
//
// Retour de test : « après saisie du numéro de téléphone avec le PIN, je
// ne suis pas ramené directement sur la page suivante ; je suis obligé
// de fermer l'application et de revenir avant que ça marche. »
//
// Le serveur répond alors 202 plutôt qu'un jeton. Ce test rejoue le
// parcours dans l'application entière — vrai routeur, vraie coquille de
// navigation, vrai `AuthFlowBloc` — avec un seul élément simulé : la
// réponse du serveur. C'est le seul moyen d'observer la navigation sans
// faire envoyer un vrai code par SMS.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/app.dart';
import 'package:kora_app_passenger/core/constants/storage_keys.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/env/app_env.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_session.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_challenge.dart';
import 'package:kora_app_passenger/features/auth/domain/repositories/auth_repository.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_data_source.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../support/stub_auth_repository.dart';

/// Le serveur d'un appareil inconnu : 202, un défi, pas de session.
class UnknownDeviceAuthRepository extends StubAuthRepository {
  @override
  Future<Either<Failure, LoginOutcome>> login({
    required String identifier,
    required String pin,
    OtpVerification? verification,
  }) async {
    calls.add('login:$identifier');

    // Le second appel porte le jeton de vérification : l'appareil vient
    // d'être validé, la connexion aboutit.
    if (verification != null) {
      return Right(LoginSucceeded(StubAuthRepository.session));
    }

    return Right(
      LoginNeedsOtp(
        OtpChallengeRef(
          uuid: 'challenge-appareil',
          expiresAt: DateTime.now().add(const Duration(minutes: 15)),
        ),
      ),
    );
  }
}

void main() {
  late Map<String, String> secureStorage;

  setUpAll(() async {
    AppEnv.current = AppEnv.dev;
    await initializeDateFormatting(KoraDateFormats.locale);
    await AppLocalization.preloadAll();
  });

  /// Reconstruit l'injecteur. Les préférences se posent **avant** :
  /// `setupInjector` retient l'instance de `SharedPreferences`.
  Future<void> boot({required bool onboardingSeen}) async {
    await getIt.reset();
    SharedPreferences.setMockInitialValues(
      onboardingSeen ? {StorageKeys.onboardingSeen: true} : {},
    );
    await setupInjector();
  }

  setUp(() async {
    secureStorage = {};
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

    await boot(onboardingSeen: true);

    await getIt.unregister<BookingDataSource>();
    getIt.registerLazySingleton<BookingDataSource>(
      () => BookingMockDataSource(),
    );

    await getIt.unregister<AuthRepository>();
    getIt.registerLazySingleton<AuthRepository>(
      () => UnknownDeviceAuthRepository(),
    );
  });

  tearDown(() {
    AppRouter.resetForTesting();
    getIt.reset();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
          null,
        );
  });

  /// Laisse tourner quelques frames : l'écran de bienvenue anime son bus
  /// en boucle, `pumpAndSettle` n'y terminerait jamais.
  Future<void> settle(WidgetTester tester, {int frames = 12}) async {
    for (var i = 0; i < frames; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
  }

  testWidgets('le numéro et le PIN mènent à la vérification de l’appareil', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(const KoraApp());
    await settle(tester);

    // Bienvenue → connexion, comme sur un téléphone neuf.
    await tester.tap(find.text('J’ai déjà un compte'));
    await settle(tester);
    expect(find.text('Votre PIN KORA'), findsOneWidget);

    // Le numéro, puis les quatre chiffres.
    await tester.enterText(find.byType(TextField).last, '01 97 42 18 60');
    await settle(tester, frames: 3);

    for (final key in ['1', '2', '3', '4']) {
      await tester.tap(find.text(key));
      await tester.pump();
    }
    await tester.tap(find.text('✓'));
    await settle(tester);

    // Le serveur a répondu 202 : l'écran qui explique le refus doit
    // s'ouvrir tout seul. C'est ici que le parcours restait bloqué.
    expect(
      find.text('Nouvel appareil détecté'),
      findsOneWidget,
      reason: 'le 202 doit ouvrir la vérification sans relancer l’app',
    );
  });

  testWidgets('même chose sur un téléphone neuf, onboarding compris', (
    tester,
  ) async {
    // Le cas du retour de test : l'app n'a jamais été ouverte. Elle
    // commence donc par se présenter, et le drapeau « onboarding vu »
    // bascule en cours de session — ce que le premier test ne couvre
    // pas, puisqu'il part d'une app déjà rodée.
    await boot(onboardingSeen: false);
    await getIt.unregister<BookingDataSource>();
    getIt.registerLazySingleton<BookingDataSource>(
      () => BookingMockDataSource(),
    );
    await getIt.unregister<AuthRepository>();
    getIt.registerLazySingleton<AuthRepository>(
      () => UnknownDeviceAuthRepository(),
    );

    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(const KoraApp());
    await settle(tester);

    await tester.tap(find.text('Passer'));
    await settle(tester);

    await tester.tap(find.text('J’ai déjà un compte'));
    await settle(tester);
    expect(find.text('Votre PIN KORA'), findsOneWidget);

    await tester.enterText(find.byType(TextField).last, '01 97 42 18 60');
    await settle(tester, frames: 3);

    for (final key in ['1', '2', '3', '4']) {
      await tester.tap(find.text(key));
      await tester.pump();
    }
    await tester.tap(find.text('✓'));
    await settle(tester);

    expect(
      find.text('Nouvel appareil détecté'),
      findsOneWidget,
      reason: 'le 202 doit ouvrir la vérification dès la première session',
    );
  });
}
