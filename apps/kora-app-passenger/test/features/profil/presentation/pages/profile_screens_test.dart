// Tests des trois écrans du profil : mes informations, sécurité et
// préférences. Ils partagent le même montage — une session ouverte, le
// dépôt bouchonné dans le service locator — d'où un seul fichier.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_device.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_session.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_user.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/device_platform.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/user_preferences.dart';
import 'package:kora_app_passenger/features/auth/domain/repositories/auth_repository.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_bloc.dart';
import 'package:kora_app_passenger/features/language/data/datasources/language_local_data_source.dart';
import 'package:kora_app_passenger/features/language/data/repositories/language_repository_impl.dart';
import 'package:kora_app_passenger/features/language/presentation/bloc/language_bloc.dart';
import 'package:kora_app_passenger/features/profil/presentation/pages/preferences_page.dart';
import 'package:kora_app_passenger/features/profil/presentation/pages/profile_edit_page.dart';
import 'package:kora_app_passenger/features/profil/presentation/pages/security_page.dart';
import 'package:kora_app_passenger/features/theme/data/datasources/theme_local_data_source.dart';
import 'package:kora_app_passenger/features/theme/data/repositories/theme_repository_impl.dart';
import 'package:kora_app_passenger/features/theme/presentation/bloc/theme_bloc.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../../support/stub_auth_repository.dart';

/// Dépôt qui rend une liste d'appareils, dont celui de la session.
class _DevicesRepository extends StubAuthRepository {
  _DevicesRepository({super.failure});

  static final current = AuthDevice(
    uuid: 'device-courant',
    name: 'Infinix HOT 12i',
    platform: DevicePlatform.android,
    status: 'trusted',
    lastSeenAt: DateTime(2026, 8, 20),
  );

  static final other = AuthDevice(
    uuid: 'device-ancien',
    name: 'Tecno Spark 20',
    platform: DevicePlatform.android,
    status: 'trusted',
    lastSeenAt: DateTime(2026, 7, 12),
  );

  List<AuthDevice> stored = [current, other];

  @override
  Future<Either<Failure, List<AuthDevice>>> devices() async =>
      _record('devices', stored);

  @override
  Future<Either<Failure, void>> revokeDevice(String uuid) async {
    stored = stored.where((device) => device.uuid != uuid).toList();
    return _record('revokeDevice:$uuid', null);
  }

  Either<Failure, T> _record<T>(String call, T value) {
    calls.add(call);
    final error = failure;
    return error == null ? Right(value) : Left(error);
  }
}

void main() {
  late StubAuthRepository repository;

  /// Session ouverte sur l'appareil « courant » de [_DevicesRepository].
  final session = AuthSession(
    token: 'token-de-test',
    expiresAt: DateTime(2099),
    user: const AuthUser(
      uuid: 'user-1',
      name: 'Aïcha HOUNKPÈ',
      email: 'aicha@example.com',
      phone: '0197123456',
      countryCode: '+229',
    ),
    device: _DevicesRepository.current,
  );

  setUpAll(() async {
    // Les cartes d'appareil affichent une date.
    await initializeDateFormatting();
  });

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    repository = StubAuthRepository();
  });

  tearDown(() => getIt.reset());

  /// Le toast de confirmation garde un minuteur de 2,6 s : sans le
  /// laisser finir, le test s'achève avec un timer en attente.
  Future<void> settleToast(WidgetTester tester) async {
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
  }

  Future<void> pump(WidgetTester tester, Widget page, {AuthUser? user}) async {
    tester.view.physicalSize = const Size(390 * 3, 1400 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    // L'écran « Mes informations » relit le profil à l'ouverture : le
    // bouchon doit rendre celui de la session, téléphone compris.
    // Un test peut ouvrir la session sur un autre profil — un numéro
    // gardé au format international, par exemple.
    final opened = user == null
        ? session
        : AuthSession(
            token: session.token,
            expiresAt: session.expiresAt,
            user: user,
            device: session.device,
          );
    repository.refreshedUser = opened.user;
    getIt.registerLazySingleton<AuthRepository>(() => repository);

    final auth = AuthBloc(repository)..add(AuthSessionOpened(opened));
    addTearDown(auth.close);

    final preferences = await SharedPreferences.getInstance();
    final theme = ThemeBloc(
      ThemeRepositoryImpl(ThemeLocalDataSourceImpl(preferences)),
    )..add(const ThemeStarted());
    addTearDown(theme.close);
    final language = LanguageBloc(
      LanguageRepositoryImpl(LanguageLocalDataSourceImpl(preferences)),
    )..add(const LanguageStarted());
    addTearDown(language.close);

    await tester.pumpWidget(
      MultiBlocProvider(
        providers: [
          BlocProvider<AuthBloc>.value(value: auth),
          BlocProvider<ThemeBloc>.value(value: theme),
          BlocProvider<LanguageBloc>.value(value: language),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          locale: const Locale('fr'),
          supportedLocales: const [Locale('fr'), Locale('en')],
          localizationsDelegates: const [
            AppLocalization.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          home: page,
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  group('Mes informations', () {
    testWidgets('sépare le nom du compte en prénom et nom', (tester) async {
      await pump(tester, const ProfileEditPage());

      // L'API ne connaît qu'un `name` : le premier mot est le prénom, le
      // reste le nom.
      expect(find.widgetWithText(TextField, 'Aïcha'), findsOneWidget);
      expect(find.widgetWithText(TextField, 'HOUNKPÈ'), findsOneWidget);
      // Le téléphone est affiché avec son indicatif, et verrouillé.
      expect(find.widgetWithText(TextField, '+229 0197123456'), findsOneWidget);
    });

    testWidgets('un numéro déjà international ne prend pas un second '
        'indicatif', (tester) async {
      // Le serveur garde parfois le numéro au format international, sans
      // `country_code` : le champ affichait « +229 +2290197123456 ».
      await pump(
        tester,
        const ProfileEditPage(),
        user: const AuthUser(
          uuid: 'user-1',
          name: 'Aïcha HOUNKPÈ',
          email: 'aicha@example.com',
          phone: '+2290197123456',
        ),
      );

      expect(find.widgetWithText(TextField, '+229 0197123456'), findsOneWidget);
    });

    testWidgets('recolle les deux champs avant l’envoi', (tester) async {
      await pump(tester, const ProfileEditPage());

      await tester.enterText(find.byType(TextField).first, 'Aïcha Marie');
      await tester.tap(find.text('Enregistrer'));
      await tester.pumpAndSettle();
      await settleToast(tester);

      expect(
        repository.calls,
        contains('updateProfile:Aïcha Marie HOUNKPÈ/+2290197123456'),
      );
    });

    testWidgets('refuse un prénom vide et une adresse mal formée', (
      tester,
    ) async {
      await pump(tester, const ProfileEditPage());

      await tester.enterText(find.byType(TextField).first, '');
      await tester.enterText(find.byType(TextField).at(3), 'pas-une-adresse');
      await tester.tap(find.text('Enregistrer'));
      await tester.pumpAndSettle();

      expect(find.text('Indiquez votre prénom'), findsOneWidget);
      expect(find.text('Adresse e-mail invalide'), findsOneWidget);
      // Seule la relecture d'ouverture a eu lieu : rien n'a été envoyé.
      expect(repository.calls, ['refreshUser']);
    });
  });

  group('Sécurité', () {
    testWidgets('liste les appareils et marque celui de la session', (
      tester,
    ) async {
      repository = _DevicesRepository();
      await pump(tester, const SecurityPage());

      expect(find.text('Infinix HOT 12i'), findsOneWidget);
      expect(find.text('Tecno Spark 20'), findsOneWidget);

      // L'appareil courant porte une pastille, pas un lien de
      // révocation : s'en retirer l'accès depuis lui-même n'a pas de sens.
      expect(find.text('ACTIF'), findsOneWidget);
      expect(find.text('Révoquer'), findsOneWidget);
    });

    testWidgets('révoque un appareil et relit la liste', (tester) async {
      final devices = _DevicesRepository();
      repository = devices;
      await pump(tester, const SecurityPage());

      await tester.tap(find.text('Révoquer'));

      // Le dialog anime son pictogramme en continu : `pumpAndSettle`
      // n'aurait jamais la main.
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));

      // Le second « Révoquer » est celui du dialog.
      await tester.tap(find.text('Révoquer').last);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      await settleToast(tester);

      expect(devices.calls, [
        'devices',
        'revokeDevice:device-ancien',
        'devices',
      ]);
      expect(find.text('Tecno Spark 20'), findsNothing);
      expect(find.text('Infinix HOT 12i'), findsOneWidget);
    });

    testWidgets('annuler la révocation ne touche à rien', (tester) async {
      // Le geste est irréversible et vise un autre téléphone que celui
      // qu'on tient : son propriétaire perdrait sa session sans
      // comprendre pourquoi.
      final devices = _DevicesRepository();
      repository = devices;
      await pump(tester, const SecurityPage());

      await tester.tap(find.text('Révoquer'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));

      expect(find.text('Révoquer cet appareil ?'), findsOneWidget);

      await tester.tap(find.text('Annuler'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));

      // La liste a été lue au démarrage, et rien de plus.
      expect(devices.calls, ['devices']);
      expect(find.text('Tecno Spark 20'), findsOneWidget);
    });

    testWidgets('propose de réessayer quand la liste échoue', (tester) async {
      repository = _DevicesRepository(
        failure: const ServerFailure('Serveur injoignable'),
      );
      await pump(tester, const SecurityPage());

      expect(
        find.text('Vos appareils n’ont pas pu être chargés'),
        findsOneWidget,
      );
      expect(find.text('Réessayer'), findsOneWidget);
    });

    // Apple l'exige (règle 5.1.1(v)) : une app qui crée des comptes doit
    // permettre d'en demander la suppression depuis l'app elle-même.
    final deletionPage = Uri.parse(
      'https://prod.koraexpress.app/account-deletion',
    );

    testWidgets('ouvre la page de suppression du compte', (tester) async {
      repository = _DevicesRepository();
      final opened = <Uri>[];
      await pump(
        tester,
        SecurityPage(
          accountDeletionUrl: deletionPage,
          launcher: (uri) async {
            opened.add(uri);
            return true;
          },
        ),
      );

      await tester.scrollUntilVisible(find.text('Supprimer mon compte'), 200);
      await tester.tap(find.text('Supprimer mon compte'));
      await tester.pumpAndSettle();

      expect(opened, [deletionPage]);
    });

    testWidgets('donne l’adresse si aucun navigateur ne s’ouvre', (
      tester,
    ) async {
      repository = _DevicesRepository();
      await pump(
        tester,
        SecurityPage(
          accountDeletionUrl: deletionPage,
          launcher: (_) async => false,
        ),
      );

      await tester.scrollUntilVisible(find.text('Supprimer mon compte'), 200);
      await tester.tap(find.text('Supprimer mon compte'));
      // Le toast s'insère dans l'Overlay une frame plus tard, puis entre
      // en glissant.
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));

      // Un appui sans effet laisserait croire la demande partie.
      expect(
        find.textContaining('prod.koraexpress.app/account-deletion'),
        findsOneWidget,
      );
      // Laisse le toast finir son cycle — entrée, attente, sortie : sinon
      // sa minuterie survit au test.
      await tester.pumpAndSettle();
      await settleToast(tester);
    });
  });

  group('Préférences', () {
    testWidgets('affiche les réglages du compte', (tester) async {
      repository.storedPreferences = const UserPreferences(
        pushNotifications: true,
        whatsappNotifications: false,
        smsNotifications: false,
        emailNotifications: true,
      );
      await pump(tester, const PreferencesPage());

      expect(find.text('Notifications push'), findsOneWidget);
      expect(find.text('Messages WhatsApp'), findsOneWidget);
      expect(find.text('Messages SMS'), findsOneWidget);
      expect(find.text('Emails'), findsOneWidget);

      final toggles = tester
          .widgetList<KoraToggle>(find.byType(KoraToggle))
          .toList();
      expect(toggles[0].value, isTrue);
      expect(toggles[1].value, isFalse);
      expect(toggles[2].value, isFalse);
      expect(toggles[3].value, isTrue);
    });

    testWidgets('enregistre un interrupteur basculé', (tester) async {
      await pump(tester, const PreferencesPage());

      await tester.tap(find.byType(KoraToggle).first);
      await tester.pumpAndSettle();

      expect(repository.calls, ['preferences', 'savePreferences']);
      expect(repository.storedPreferences.pushNotifications, isFalse);
    });

    testWidgets('remet l’interrupteur en place si le serveur refuse', (
      tester,
    ) async {
      repository = StubAuthRepository(
        failure: const ServerFailure('Serveur injoignable'),
      );
      await pump(tester, const PreferencesPage());

      // Le chargement échoue déjà : l'écran propose de réessayer plutôt
      // que d'afficher des réglages inventés.
      expect(
        find.text('Vos préférences n’ont pas pu être chargées'),
        findsOneWidget,
      );
      expect(find.byType(KoraToggle), findsNothing);
    });

    testWidgets('le mode sombre suit le bloc de thème', (tester) async {
      await pump(tester, const PreferencesPage());

      expect(find.text('Disponible, non activé'), findsOneWidget);

      await tester.tap(find.byType(KoraToggle).last);
      await tester.pumpAndSettle();

      expect(find.text('Activé'), findsOneWidget);
      expect(repository.storedPreferences.theme, 'dark');
    });
  });
}
