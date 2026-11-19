// Tests de l'écran de création du PIN : saisie, effacement, validation
// incomplète, double saisie et transmission du code choisi.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_session.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_challenge.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_purpose.dart';
import 'package:kora_app_passenger/features/auth/domain/repositories/auth_repository.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_flow_bloc.dart';
import 'package:kora_app_passenger/features/auth/presentation/pages/create_pin_page.dart';

import '../../../../support/stub_auth_repository.dart';

/// Dépôt dont la création de compte se heurte au 409 `account_exists` :
/// le code a bien été vérifié, c'est la dernière étape qui refuse.
class _ExistingAccountRepository extends StubAuthRepository {
  @override
  Future<Either<Failure, AuthSession>> completeRegistration({
    required OtpVerification verification,
    required String pin,
    String? name,
    String? email,
    String? phone,
    String? countryCode,
    String? referralCode,
  }) async => const Left(
    AccountExistsFailure('Un compte existe déjà pour cet identifiant.'),
  );
}

/// Dépôt qui refuse le code de parrainage, comme le fait l'API : un 422
/// dont la raison est écrite pour le champ `referral_code`.
class _RefusedReferralRepository extends StubAuthRepository {
  @override
  Future<Either<Failure, AuthSession>> completeRegistration({
    required OtpVerification verification,
    required String pin,
    String? name,
    String? email,
    String? phone,
    String? countryCode,
    String? referralCode,
  }) async {
    final sent = referralCode == null || referralCode.isEmpty
        ? ''
        : ':parrain=$referralCode';
    calls.add('completeRegistration:$pin$sent');

    // Sans code, plus rien à refuser : le compte se crée.
    if (sent.isEmpty) return Right(StubAuthRepository.session);

    return const Left(
      ValidationFailure(
        'Ce code de parrainage est inconnu.',
        field: 'referral_code',
      ),
    );
  }
}

void main() {
  late List<String> submitted;

  Future<void> pumpCreatePin(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    submitted = [];

    await tester.pumpWidget(
      _app(_withFlow(CreatePinPage(onSubmit: submitted.add))),
    );
    await tester.pumpAndSettle();
  }

  /// Compte les pastilles déjà remplies. Hors écran navy, elles se
  /// remplissent en bleu KORA.
  int filledDots(WidgetTester tester) =>
      tester.widgetList<Container>(find.byType(Container)).where((container) {
        final decoration = container.decoration;
        return decoration is BoxDecoration &&
            decoration.shape == BoxShape.circle &&
            decoration.color == AppColors.kora;
      }).length;

  testWidgets('affiche la consigne et un pavé complet', (tester) async {
    await pumpCreatePin(tester);

    expect(find.text('Créez votre PIN'), findsOneWidget);
    expect(find.textContaining('4 chiffres faciles à retenir'), findsOneWidget);

    for (final key in ['1', '5', '9', '0', '⌫', '✓']) {
      expect(find.text(key), findsOneWidget, reason: 'touche $key absente');
    }
    expect(filledDots(tester), 0);
  });

  testWidgets('remplit une pastille par chiffre, sans dépasser quatre', (
    tester,
  ) async {
    await pumpCreatePin(tester);

    for (final key in ['1', '2', '3']) {
      await tester.tap(find.text(key));
      await tester.pump();
    }
    expect(filledDots(tester), 3);

    for (final key in ['4', '5']) {
      await tester.tap(find.text(key));
      await tester.pump();
    }
    expect(filledDots(tester), 4);
  });

  testWidgets('efface le dernier chiffre saisi', (tester) async {
    await pumpCreatePin(tester);

    await tester.tap(find.text('7'));
    await tester.tap(find.text('8'));
    await tester.pump();
    expect(filledDots(tester), 2);

    await tester.tap(find.text('⌫'));
    await tester.pump();
    expect(filledDots(tester), 1);
  });

  testWidgets('refuse un PIN incomplet', (tester) async {
    await pumpCreatePin(tester);

    await tester.tap(find.text('1'));
    await tester.tap(find.text('2'));
    await tester.pump();

    await tester.tap(find.text('Continuer'));
    await tester.pump();

    expect(find.text('Choisissez vos 4 chiffres'), findsOneWidget);
    expect(submitted, isEmpty);
  });

  /// Saisit [pin] puis valide, comme le ferait un utilisateur.
  Future<void> enter(
    WidgetTester tester,
    String pin, {
    String key = 'Continuer',
  }) async {
    for (final digit in pin.split('')) {
      await tester.tap(find.text(digit));
      await tester.pump();
    }
    await tester.tap(find.text(key));
    await tester.pump();
  }

  testWidgets('demande le code une seconde fois avant de le transmettre', (
    tester,
  ) async {
    await pumpCreatePin(tester);

    await enter(tester, '1234');

    // Le premier code ne part pas : l'écran bascule sur la confirmation,
    // pastilles remises à zéro.
    expect(submitted, isEmpty);
    expect(find.text('Confirmez votre PIN'), findsOneWidget);
    expect(filledDots(tester), 0);

    await enter(tester, '1234');

    expect(submitted, ['1234']);
    expect(find.text('Choisissez vos 4 chiffres'), findsNothing);
  });

  testWidgets('refuse deux codes différents et repart du choix', (
    tester,
  ) async {
    await pumpCreatePin(tester);

    await enter(tester, '1234');
    await enter(tester, '1235');

    expect(submitted, isEmpty);
    expect(find.text('Les deux codes ne correspondent pas'), findsOneWidget);
    // On revient au premier temps : sinon l'utilisateur confirmerait un
    // code qu'il n'a peut-être pas voulu.
    expect(find.text('Créez votre PIN'), findsOneWidget);
    expect(filledDots(tester), 0);

    await enter(tester, '4321');
    await enter(tester, '4321');

    expect(submitted, ['4321']);
  });

  testWidgets('la touche ✓ vaut le bouton Continuer', (tester) async {
    await pumpCreatePin(tester);

    await enter(tester, '9876', key: '✓');
    await enter(tester, '9876', key: '✓');

    expect(submitted, ['9876']);
  });

  testWidgets('accepte des libellés dictés par l’appelant', (tester) async {
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      _app(
        _withFlow(
          const CreatePinPage(
            title: 'Confirmez votre PIN',
            subtitle: 'Saisissez à nouveau votre code personnel.',
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Confirmez votre PIN'), findsOneWidget);
    expect(
      find.text('Saisissez à nouveau votre code personnel.'),
      findsOneWidget,
    );
  });

  testWidgets('un compte déjà créé mène à la connexion', (tester) async {
    // Le 409 ne tombe pas à la demande du code : il tombe **ici**, au
    // dernier appel, une fois le code vérifié et le PIN choisi. C'est
    // donc cet écran qui doit ouvrir le chemin vers la connexion, et
    // non celui de l'inscription.
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    var signedIn = false;
    final flow = AuthFlowBloc(_ExistingAccountRepository())
      ..add(const AuthFlowIdentifierSubmitted(identifier: 'aicha@example.com'));
    addTearDown(flow.close);
    await tester.pumpAndSettle();

    // Le code vérifié amène au choix du PIN.
    flow.add(const AuthFlowCodeSubmitted('123456'));
    await tester.pumpAndSettle();
    expect(flow.state.step, AuthFlowStep.pin);

    await tester.pumpWidget(
      _app(
        BlocProvider<AuthFlowBloc>.value(
          value: flow,
          child: CreatePinPage(onSignIn: () => signedIn = true),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Les deux temps du PIN, puis le refus du serveur.
    for (var pass = 0; pass < 2; pass++) {
      for (final digit in ['1', '2', '3', '4']) {
        await tester.tap(find.text(digit));
      }
      await tester.tap(find.text('✓'));
      await tester.pumpAndSettle();
    }

    expect(
      find.text('Un compte existe déjà pour cet identifiant.'),
      findsOneWidget,
    );
    expect(find.text('Ce compte existe déjà.'), findsOneWidget);

    await tester.tap(find.text('Connectez-vous plutôt'));
    await tester.pumpAndSettle();

    expect(signedIn, isTrue);
  });

  /// Monte l'écran avec son parcours arrivé au choix du PIN, comme après
  /// un code vérifié.
  Future<AuthFlowBloc> pumpAtPinStep(
    WidgetTester tester,
    AuthRepository repository, {
    OtpPurpose purpose = OtpPurpose.registration,
    String? referralCode,
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 1200 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final flow = AuthFlowBloc(repository)
      ..add(
        AuthFlowIdentifierSubmitted(
          identifier: 'aicha@example.com',
          purpose: purpose,
          referralCode: referralCode,
        ),
      );
    addTearDown(flow.close);
    await tester.pumpAndSettle();

    flow.add(const AuthFlowCodeSubmitted('123456'));
    await tester.pumpAndSettle();

    await tester.pumpWidget(
      _app(
        BlocProvider<AuthFlowBloc>.value(
          value: flow,
          child: const CreatePinPage(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    return flow;
  }

  /// Les deux temps du PIN, comme le ferait un utilisateur.
  Future<void> enterPinTwice(WidgetTester tester, String pin) async {
    for (var pass = 0; pass < 2; pass++) {
      for (final digit in pin.split('')) {
        await tester.tap(find.text(digit));
      }
      await tester.tap(find.text('✓'));
      await tester.pumpAndSettle();
    }
  }

  group('code de parrainage refusé', () {
    testWidgets('le refus se lit ici, PIN toujours en main', (tester) async {
      // Le code a été saisi au formulaire, deux écrans plus tôt, mais
      // c'est ici qu'il se rattache — et ici que le serveur le refuse.
      final repository = _RefusedReferralRepository();
      final flow = await pumpAtPinStep(
        tester,
        repository,
        referralCode: 'INCONNU',
      );

      await enterPinTwice(tester, '1234');

      expect(find.text('Ce code de parrainage est inconnu.'), findsOneWidget);
      // Le message du serveur, et lui seul : annoncer en plus un échec
      // au milieu de l'écran laisserait croire qu'il faut tout refaire.
      expect(flow.state.errorMessage, isEmpty);
      // Le parcours n'a pas reculé : le code OTP vaut toujours, le PIN
      // est gardé.
      expect(flow.state.step, AuthFlowStep.pin);
      expect(flow.state.verification, isNotNull);
    });

    testWidgets('on peut terminer sans le code', (tester) async {
      // Revenir au formulaire redemanderait un code OTP : on laisse
      // plutôt finir l'inscription sans parrain. Le Wallet le
      // rattrapera tant que le voyageur n'a pas voyagé.
      final repository = _RefusedReferralRepository();
      final flow = await pumpAtPinStep(
        tester,
        repository,
        referralCode: 'INCONNU',
      );

      await enterPinTwice(tester, '1234');
      expect(find.text('Continuer sans ce code'), findsOneWidget);

      await tester.tap(find.text('Continuer sans ce code'));
      await tester.pumpAndSettle();

      // Le second envoi part sans le code, et le compte se crée.
      expect(repository.calls.last, 'completeRegistration:1234');
      expect(flow.state.step, AuthFlowStep.done);
    });

    testWidgets('rien à proposer quand le serveur ne refuse rien', (
      tester,
    ) async {
      await pumpAtPinStep(tester, StubAuthRepository());

      await enterPinTwice(tester, '1234');

      expect(find.text('Continuer sans ce code'), findsNothing);
    });
  });
}

/// Le `MaterialApp` des tests : thème KORA et textes français, que les
/// écrans lisent par `context.l10n`.
Widget _app(Widget home) => MaterialApp(
  theme: AppTheme.light,
  locale: const Locale('fr'),
  supportedLocales: const [Locale('fr'), Locale('en')],
  localizationsDelegates: const [
    AppLocalization.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  home: home,
);

/// Les écrans du parcours lisent un [AuthFlowBloc] dans leur arbre. Ces
/// tests portent sur la saisie, pas sur le réseau : le dépôt bouchonné
/// répond succès et n'est pas interrogé ici.
Widget _withFlow(Widget child) {
  return BlocProvider<AuthFlowBloc>(
    create: (_) => AuthFlowBloc(StubAuthRepository()),
    child: child,
  );
}
