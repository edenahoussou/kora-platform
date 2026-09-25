// Tests de l'écran de connexion : saisie du PIN, effacement, validation
// incomplète, et entrée dans l'app.

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_purpose.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_flow_bloc.dart';
import 'package:kora_app_passenger/features/auth/presentation/pages/login_page.dart';
import 'package:kora_app_passenger/features/auth/presentation/widgets/pin_pad.dart';

import '../../../../support/stub_auth_repository.dart';

void main() {
  late int successes;
  late StubAuthRepository repository;
  late AuthFlowBloc flow;

  Future<void> pumpLogin(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    successes = 0;
    repository = StubAuthRepository();
    flow = AuthFlowBloc(repository);
    addTearDown(flow.close);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: _withFlow(
          flow,
          // L'app se souvient du dernier compte : la maquette ne demande
          // que le PIN.
          LoginPage(identifier: '+2290197421860', onSuccess: () => successes++),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  /// Compte les pastilles déjà remplies (dorées).
  int filledDots(WidgetTester tester) =>
      tester.widgetList<Container>(find.byType(Container)).where((container) {
        final decoration = container.decoration;
        return decoration is BoxDecoration &&
            decoration.shape == BoxShape.circle &&
            decoration.color == AppColors.gold;
      }).length;

  testWidgets('affiche le compte et un pavé complet', (tester) async {
    await pumpLogin(tester);

    expect(find.text('Votre PIN KORA'), findsOneWidget);
    expect(find.text('+2290197421860'), findsOneWidget);

    for (final key in ['1', '5', '9', '0', '⌫', '✓']) {
      expect(find.text(key), findsOneWidget, reason: 'touche $key absente');
    }
    expect(filledDots(tester), 0);
  });

  testWidgets('remplit une pastille par chiffre, sans dépasser quatre', (
    tester,
  ) async {
    await pumpLogin(tester);

    for (final key in ['1', '2', '3']) {
      await tester.tap(find.text(key));
      await tester.pump();
    }
    expect(filledDots(tester), 3);

    // Un cinquième chiffre ne s'ajoute pas.
    for (final key in ['4', '5']) {
      await tester.tap(find.text(key));
      await tester.pump();
    }
    expect(filledDots(tester), 4);
  });

  testWidgets('efface le dernier chiffre saisi', (tester) async {
    await pumpLogin(tester);

    await tester.tap(find.text('7'));
    await tester.tap(find.text('8'));
    await tester.pump();
    expect(filledDots(tester), 2);

    await tester.tap(find.text('⌫'));
    await tester.pump();
    expect(filledDots(tester), 1);
  });

  testWidgets('refuse un PIN incomplet', (tester) async {
    await pumpLogin(tester);

    await tester.tap(find.text('1'));
    await tester.tap(find.text('2'));
    await tester.pump();

    await tester.tap(find.text('✓'));
    await tester.pump();

    expect(find.text('Saisissez vos 4 chiffres'), findsOneWidget);
    expect(successes, 0);
  });

  testWidgets('ouvre l’app une fois les quatre chiffres saisis', (
    tester,
  ) async {
    await pumpLogin(tester);

    for (final key in ['1', '2', '3', '4']) {
      await tester.tap(find.text(key));
      await tester.pump();
    }
    await tester.tap(find.text('✓'));
    await tester.pump();

    expect(successes, 1);
    expect(find.text('Saisissez vos 4 chiffres'), findsNothing);
  });

  testWidgets('« PIN oublié ? » demande un code de réinitialisation', (
    tester,
  ) async {
    await pumpLogin(tester);

    await tester.tap(find.text('PIN oublié ?'));
    await tester.pumpAndSettle();
    // Le lien demande d'abord le compte ; c'est « Continuer » qui envoie
    // le code.
    await tester.tap(find.text('Continuer'));
    await tester.pumpAndSettle();

    // Même parcours que l'inscription, autre motif : le code part, et
    // l'écran de vérification prend la suite.
    expect(repository.calls, ['requestOtp:pin_reset']);
    expect(flow.state.step, AuthFlowStep.code);
    expect(flow.state.purpose, OtpPurpose.pinReset);
  });

  testWidgets('le lien du nouvel appareil ouvre l’explication', (tester) async {
    await pumpLogin(tester);

    await tester.tap(find.text('Connexion sur un nouvel appareil'));
    await tester.pumpAndSettle();
    // Le lien demande d'abord le compte visé ; c'est « Continuer » qui
    // engage le parcours.
    await tester.tap(find.text('Continuer'));
    await tester.pumpAndSettle();

    // Rien n'est demandé au serveur ici : l'écran explique d'abord.
    expect(repository.calls, isEmpty);
    expect(flow.state.step, AuthFlowStep.newDevice);
    expect(flow.state.purpose, OtpPurpose.loginNewDevice);
  });

  testWidgets('un compte vide laisse saisir son adresse', (tester) async {
    // Le parcours remis à zéro repasse par ici avec une chaîne vide,
    // jamais un `null`. S'y fier rendait le champ inutile : il
    // s'affichait, on pouvait y taper, mais la saisie n'était pas lue et
    // l'écran répondait « compte requis » à chaque essai.
    repository = StubAuthRepository();
    flow = AuthFlowBloc(repository);
    addTearDown(flow.close);

    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: _withFlow(flow, const LoginPage(identifier: '')),
      ),
    );
    await tester.pumpAndSettle();

    // L'écran s'ouvre sur l'adresse : c'est avec elle que les comptes
    // ont été créés.
    await tester.enterText(find.byType(TextField).first, 'kevine@example.com');
    for (final key in ['1', '2', '3', '4']) {
      await tester.tap(find.text(key));
    }
    await tester.tap(find.text('✓'));
    await tester.pumpAndSettle();

    expect(repository.calls, contains('login:kevine@example.com'));
  });

  testWidgets('le lien bascule la saisie vers le téléphone', (tester) async {
    // Les deux façons de se nommer vivent sur le même champ `identifier`
    // côté API : le choix est celui de l'écran, et il tient dans ce lien.
    repository = StubAuthRepository();
    flow = AuthFlowBloc(repository);
    addTearDown(flow.close);

    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: _withFlow(flow, const LoginPage(identifier: '')),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Connexion avec votre numéro de téléphone ?'));
    await tester.pumpAndSettle();

    // L'indicatif apparaît, et le lien propose le chemin inverse.
    expect(find.text('🇧🇯  +229'), findsOneWidget);
    expect(find.text('Connexion avec votre adresse e-mail ?'), findsOneWidget);

    // Tel qu'on le compose au téléphone : dix chiffres, sans indicatif —
    // c'est la liste déroulante qui le porte.
    await tester.enterText(find.byType(TextField).last, '01 97 42 18 60');
    for (final key in ['1', '2', '3', '4']) {
      await tester.tap(find.text(key));
    }
    await tester.tap(find.text('✓'));
    await tester.pumpAndSettle();

    // Le serveur reçoit le numéro au format international, indicatif
    // recollé et séparateurs retirés.
    expect(repository.calls, contains('login:+2290197421860'));
  });

  testWidgets('un numéro incomplet ne part pas au serveur', (tester) async {
    // Sans ce garde, quatre chiffres de PIN sur un numéro à moitié tapé
    // partaient quand même, pour revenir en « identifiants invalides » —
    // un message qui accuse le PIN alors que c'est le numéro qui manque.
    repository = StubAuthRepository();
    flow = AuthFlowBloc(repository);
    addTearDown(flow.close);

    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: _withFlow(flow, const LoginPage(identifier: '')),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Connexion avec votre numéro de téléphone ?'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField).last, '01 97 42');
    for (final key in ['1', '2', '3', '4']) {
      await tester.tap(find.text(key));
    }
    await tester.tap(find.text('✓'));
    await tester.pumpAndSettle();

    expect(find.text('Numéro incomplet'), findsOneWidget);
    expect(repository.calls, isEmpty);
  });
  testWidgets('« nouvel appareil » ne demande que le numéro', (tester) async {
    // La vérification part d'un compte, pas d'un code : laisser le pavé à
    // l'écran faisait taper quatre chiffres pour rien. Et sur un appareil
    // neuf le compte n'est même pas mémorisé — le lien répondait alors
    // « Saisissez d'abord votre numéro » au-dessus d'un clavier
    // numérique inutile.
    await pumpLogin(tester);

    expect(find.byType(PinKeypad), findsOneWidget);

    await tester.tap(find.text('Connexion sur un nouvel appareil'));
    await tester.pumpAndSettle();

    expect(find.text('Sur quel compte ?'), findsOneWidget);
    expect(find.byType(PinKeypad), findsNothing);
    // Le numéro mémorisé sert de point de départ, rangé en deux : son
    // indicatif dans la liste, le reste dans le champ. Le recoller
    // entier écrirait « +229 » deux fois.
    expect(find.text('0197421860'), findsWidgets);
    expect(find.text('🇧🇯  +229'), findsWidgets);

    await tester.tap(find.text('Continuer'));
    await tester.pumpAndSettle();

    expect(flow.state.step, AuthFlowStep.newDevice);
    expect(flow.state.identifier, '+2290197421860');
  });

  testWidgets('« PIN oublié ? » ne demande que le numéro aussi', (
    tester,
  ) async {
    // Même dead-end que le lien voisin : le PIN n'a rien à voir
    // là-dedans, c'est un code envoyé au compte qui porte la
    // réinitialisation.
    await pumpLogin(tester);

    await tester.tap(find.text('PIN oublié ?'));
    await tester.pumpAndSettle();

    expect(find.text('Quel compte réinitialiser ?'), findsOneWidget);
    expect(find.byType(PinKeypad), findsNothing);

    await tester.tap(find.text('Continuer'));
    await tester.pumpAndSettle();

    expect(flow.state.purpose, OtpPurpose.pinReset);
    expect(flow.state.identifier, '+2290197421860');
  });

  testWidgets('on peut revenir au PIN sans quitter l’écran', (tester) async {
    await pumpLogin(tester);

    await tester.tap(find.text('Connexion sur un nouvel appareil'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Revenir au PIN'));
    await tester.pumpAndSettle();

    expect(find.byType(PinKeypad), findsOneWidget);
    expect(find.text('Votre PIN KORA'), findsOneWidget);
  });
}

/// Les écrans du parcours lisent un [AuthFlowBloc] dans leur arbre. Le
/// bloc est fourni par le test, qui interroge son état après coup : les
/// liens de cet écran n'ouvrent pas de page, ils font avancer le
/// parcours.
Widget _withFlow(AuthFlowBloc flow, Widget child) {
  return BlocProvider<AuthFlowBloc>.value(value: flow, child: child);
}
