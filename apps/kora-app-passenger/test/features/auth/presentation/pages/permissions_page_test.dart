// Tests de l'écran des autorisations : les deux interrupteurs et ce
// qu'ils demandent au système, la saisie du nom et du téléphone, et
// l'envoi du profil à l'API.

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/services/app_permissions.dart';
import 'package:kora_app_passenger/features/auth/domain/repositories/auth_repository.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_bloc.dart';
import 'package:kora_app_passenger/features/auth/presentation/pages/permissions_page.dart';

import '../../../../support/stub_auth_repository.dart';

/// Système bouchonné : un test n'a ni Android ni iOS à interroger.
class _StubPermissions implements AppPermissions {
  /// Réponses du système, que chaque test ajuste avant de monter
  /// l'écran — un refus définitif, notamment.
  AppPermissionStatus location = AppPermissionStatus.granted;
  AppPermissionStatus notifications = AppPermissionStatus.granted;

  final List<String> asked = [];

  @override
  Future<AppPermissionStatus> requestLocation() async {
    asked.add('location');
    return location;
  }

  @override
  Future<AppPermissionStatus> requestNotifications() async {
    asked.add('notifications');
    return notifications;
  }
}

void main() {
  late StubAuthRepository repository;
  late _StubPermissions permissions;
  late bool done;

  setUp(() {
    repository = StubAuthRepository();
    permissions = _StubPermissions();
    done = false;

    // L'écran récupère le dépôt dans le service locator, comme l'app.
    getIt.registerLazySingleton<AuthRepository>(() => repository);
  });

  tearDown(() => getIt.reset());

  Future<void> pumpPage(
    WidgetTester tester, {
    Locale locale = const Locale('fr'),
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 1100 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    // L'écran lit l'utilisateur de la session ouverte : sans elle, il
    // n'aurait ni téléphone ni indicatif à reproposer.
    final auth = AuthBloc(repository)
      ..add(AuthSessionOpened(StubAuthRepository.session));
    addTearDown(auth.close);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: locale,
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: BlocProvider<AuthBloc>.value(
          value: auth,
          child: PermissionsPage(
            permissions: permissions,
            onDone: () => done = true,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('affiche les deux autorisations et la carte d’identité', (
    tester,
  ) async {
    await pumpPage(tester);

    expect(find.text('AUTORISATIONS'), findsOneWidget);
    expect(find.text('Presque terminé'), findsOneWidget);
    expect(find.text('Localisation'), findsOneWidget);
    expect(find.text('Notifications'), findsOneWidget);
    expect(find.text('Nom complet'), findsOneWidget);
    expect(find.text('Téléphone'), findsOneWidget);

    // Les deux interrupteurs partent éteints : rien n'est demandé au
    // système avant que l'utilisateur ne le décide.
    expect(find.byType(KoraToggle), findsNWidgets(2));
    for (final toggle in tester.widgetList<KoraToggle>(
      find.byType(KoraToggle),
    )) {
      expect(toggle.value, isFalse);
    }
    expect(permissions.asked, isEmpty);
  });

  testWidgets('demande la position au système quand on l’active', (
    tester,
  ) async {
    await pumpPage(tester);

    await tester.tap(find.byType(KoraToggle).first);
    await tester.pumpAndSettle();

    expect(permissions.asked, ['location']);
    expect(
      tester.widget<KoraToggle>(find.byType(KoraToggle).first).value,
      true,
    );
  });

  testWidgets('un refus définitif verrouille l’interrupteur et s’explique', (
    tester,
  ) async {
    permissions.notifications = AppPermissionStatus.permanentlyDenied;
    await pumpPage(tester);

    await tester.tap(find.byType(KoraToggle).last);
    await tester.pumpAndSettle();

    expect(
      find.text('Refusé — à réactiver dans les réglages du téléphone.'),
      findsOneWidget,
    );
    // Redemander n'afficherait plus rien : l'interrupteur ne réagit plus.
    final toggle = tester.widget<KoraToggle>(find.byType(KoraToggle).last);
    expect(toggle.value, isFalse);
    expect(toggle.onChanged, isNull);
  });

  testWidgets('refuse de continuer sans nom', (tester) async {
    await pumpPage(tester);

    await tester.tap(find.text('Continuer vers KORA'));
    await tester.pumpAndSettle();

    expect(find.text('Indiquez votre nom'), findsOneWidget);
    expect(repository.calls, isEmpty);
    expect(done, isFalse);
  });

  testWidgets('refuse un numéro entamé mais incomplet', (tester) async {
    await pumpPage(tester);

    await tester.enterText(find.byType(TextField).first, 'Aïcha HOUNKPÈ');
    await tester.enterText(find.byType(TextField).last, '01 97 12');
    await tester.tap(find.text('Continuer vers KORA'));
    await tester.pumpAndSettle();

    expect(find.text('Numéro incomplet'), findsOneWidget);
    expect(repository.calls, isEmpty);
  });

  testWidgets('refuse un numéro béninois saisi sans le 01', (tester) async {
    await pumpPage(tester);

    await tester.enterText(find.byType(TextField).first, 'Aïcha HOUNKPÈ');
    // Huit chiffres : la forme d'avant la réforme de 2024.
    await tester.enterText(find.byType(TextField).last, '97 12 34 56');
    await tester.tap(find.text('Continuer vers KORA'));
    await tester.pumpAndSettle();

    // Le message nomme la règle, au lieu de parler d'un numéro
    // « incomplet » qui laisserait chercher le chiffre manquant.
    expect(find.text('Un numéro Bénin commence par 01.'), findsOneWidget);
    expect(repository.calls, isEmpty);
  });

  testWidgets('envoie le nom, le numéro et l’indicatif, puis sort', (
    tester,
  ) async {
    await pumpPage(tester);

    await tester.enterText(find.byType(TextField).first, 'Aïcha HOUNKPÈ');
    await tester.enterText(find.byType(TextField).last, '01 97 12 34 56');
    await tester.tap(find.text('Continuer vers KORA'));
    await tester.pumpAndSettle();

    // Le numéro part en chiffres seuls, l'indicatif à part : c'est ce que
    // `PUT auth/me` attend (`phone` + `country_code`).
    expect(repository.calls, ['updateProfile:Aïcha HOUNKPÈ/+2290197123456']);
    expect(done, isTrue);
  });

  testWidgets('le téléphone reste facultatif', (tester) async {
    await pumpPage(tester);

    await tester.enterText(find.byType(TextField).first, 'Aïcha HOUNKPÈ');
    await tester.tap(find.text('Continuer vers KORA'));
    await tester.pumpAndSettle();

    expect(repository.calls, ['updateProfile:Aïcha HOUNKPÈ/']);
    expect(done, isTrue);
  });

  testWidgets('affiche l’erreur du serveur sans quitter l’écran', (
    tester,
  ) async {
    repository = StubAuthRepository(
      failure: const ServerFailure('Cette adresse email est déjà utilisée.'),
    );
    await pumpPage(tester);

    await tester.enterText(find.byType(TextField).first, 'Aïcha HOUNKPÈ');
    await tester.tap(find.text('Continuer vers KORA'));
    await tester.pumpAndSettle();

    expect(find.text('Cette adresse email est déjà utilisée.'), findsOneWidget);
    expect(done, isFalse);
  });

  testWidgets('« Plus tard » sort sans rien envoyer', (tester) async {
    await pumpPage(tester);

    await tester.tap(find.text('Plus tard'));
    await tester.pumpAndSettle();

    expect(repository.calls, isEmpty);
    expect(done, isTrue);
  });

  testWidgets('s’affiche en anglais quand la langue est l’anglais', (
    tester,
  ) async {
    await pumpPage(tester, locale: const Locale('en'));

    expect(find.text('Almost done'), findsOneWidget);
    expect(find.text('Full name'), findsOneWidget);
    expect(find.text('Continue to KORA'), findsOneWidget);
    expect(find.text('Later'), findsOneWidget);
  });
}
