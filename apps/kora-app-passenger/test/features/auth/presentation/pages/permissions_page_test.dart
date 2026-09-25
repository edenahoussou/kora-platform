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
import 'package:kora_app_passenger/features/auth/domain/entities/auth_session.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_user.dart';
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
    AuthSession? session,
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 1100 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    // L'écran lit l'utilisateur de la session ouverte : sans elle, il
    // n'aurait ni téléphone ni indicatif à reproposer.
    final auth = AuthBloc(repository)
      ..add(AuthSessionOpened(session ?? StubAuthRepository.session));
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

    // Le champ arrive prérempli : on l'efface pour éprouver le garde.
    await tester.enterText(find.byType(TextField).first, '');
    await tester.tap(find.text('Continuer vers KORA'));
    await tester.pumpAndSettle();

    expect(find.text('Indiquez votre nom'), findsOneWidget);
    expect(repository.calls, isEmpty);
    expect(done, isFalse);
  });

  testWidgets('le nom donné à l’inscription n’est pas redemandé', (
    tester,
  ) async {
    // Une inscription par téléphone fait saisir le nom avant le code :
    // le redemander ici, champ vide, reviendrait à le faire retaper.
    await pumpPage(tester);

    final champ = tester.widget<TextField>(find.byType(TextField).first);
    expect(champ.controller!.text, 'Aïcha HOUNKPÈ');
  });

  testWidgets('un nom dérivé de l’adresse reste à saisir', (tester) async {
    // Le serveur nomme un compte créé par e-mail d'après ce qui précède
    // le `@`. Ce n'est pas un nom : l'écran le demande pour de bon.
    final derived = AuthSession(
      token: StubAuthRepository.session.token,
      expiresAt: StubAuthRepository.session.expiresAt,
      user: const AuthUser(
        uuid: 'user-1',
        name: 'aicha',
        email: 'aicha@example.com',
      ),
    );

    await pumpPage(tester, session: derived);

    final champ = tester.widget<TextField>(find.byType(TextField).first);
    expect(champ.controller!.text, isEmpty);
  });

  testWidgets('le numéro enregistré s’affiche sans son indicatif', (
    tester,
  ) async {
    // Le serveur rend « +2290197421860 » : tel quel dans le champ, il
    // s'affichait sous un indicatif « +229 » déjà là, et repartait
    // doublé.
    await pumpPage(tester, session: StubAuthRepository.sessionWithPhone);

    final champ = tester.widget<TextField>(find.byType(TextField).last);
    expect(champ.controller!.text, '0197421860');
    expect(find.text('🇧🇯  +229'), findsOneWidget);
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
