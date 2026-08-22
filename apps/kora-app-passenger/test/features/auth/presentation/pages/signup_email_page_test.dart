// Tests de la première étape de création de compte : champ vide, format
// invalide, et transmission de l'adresse validée.

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/services/oauth_sign_in.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_flow_bloc.dart';
import 'package:kora_app_passenger/features/auth/presentation/pages/signup_email_page.dart';

import '../../../../support/stub_auth_repository.dart';

/// SDK bouchonné : un test n'ouvre pas la feuille de Google.
class _StubOAuth implements OAuthSignIn {
  _StubOAuth({this.failure});

  /// Ce que le SDK remettrait : un jeton d'accès, que l'app échange
  /// ensuite contre une session KORA.
  static const String token = 'jeton-du-fournisseur';

  final OAuthFailure? failure;

  final List<String> asked = [];

  @override
  bool isConfigured(String provider) => true;

  @override
  Future<String> signIn(String provider) async {
    asked.add(provider);
    final reason = failure;
    if (reason != null) throw OAuthSignInException(reason);

    return token;
  }

  @override
  Future<void> signOut() async {}
}

void main() {
  late String? submitted;
  late StubAuthRepository repository;
  late _StubOAuth oauth;

  // Les bouchons se posent avant chaque test, et non dans `pumpPage` :
  // plusieurs cas en remplacent un pour jouer un refus.
  setUp(() {
    submitted = null;
    repository = StubAuthRepository();
    oauth = _StubOAuth();
  });

  Future<void> pumpPage(
    WidgetTester tester, {
    Locale locale = const Locale('fr'),
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

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
        home: _withFlow(
          SignupEmailPage(onSubmit: (email) => submitted = email, oauth: oauth),
          repository,
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('affiche la consigne et le champ vide', (tester) async {
    await pumpPage(tester);

    expect(find.text('Votre adresse email'), findsOneWidget);
    expect(find.text('CRÉATION DE COMPTE'), findsOneWidget);
    expect(
      find.text(
        'Nous vous enverrons un code de vérification sécurisé par email.',
      ),
      findsOneWidget,
    );

    // Le champ ne propose pas d'adresse toute faite, juste un exemple.
    expect(find.text('vous@exemple.com'), findsOneWidget);
    final champ = tester.widget<TextField>(find.byType(TextField));
    expect(champ.controller!.text, isEmpty);
  });

  testWidgets('refuse un champ vide', (tester) async {
    await pumpPage(tester);

    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    expect(find.text('Adresse e-mail requise'), findsOneWidget);
    expect(submitted, isNull);
  });

  testWidgets('refuse une adresse mal formée', (tester) async {
    await pumpPage(tester);

    await tester.enterText(find.byType(TextField), 'aicha.example.com');
    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    expect(find.text('Adresse e-mail invalide'), findsOneWidget);
    expect(submitted, isNull);
  });

  testWidgets('transmet l’adresse validée, sans espaces superflus', (
    tester,
  ) async {
    await pumpPage(tester);

    await tester.enterText(find.byType(TextField), '  aicha@example.com  ');
    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    expect(submitted, 'aicha@example.com');
    expect(find.textContaining('invalide'), findsNothing);
  });

  testWidgets('efface l’erreur dès que la saisie reprend', (tester) async {
    await pumpPage(tester);

    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();
    expect(find.text('Adresse e-mail requise'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'a');
    await tester.pumpAndSettle();
    expect(find.text('Adresse e-mail requise'), findsNothing);
  });

  testWidgets('propose les trois fournisseurs d’identité', (tester) async {
    await pumpPage(tester);

    expect(find.text('ou'), findsOneWidget);
    expect(find.text('Continuer avec Google'), findsOneWidget);
    expect(find.text('Continuer avec Facebook'), findsOneWidget);
    expect(find.text('Continuer avec TikTok'), findsOneWidget);
  });

  testWidgets('échange le jeton Google contre une session KORA', (
    tester,
  ) async {
    await pumpPage(tester);

    await tester.tap(find.text('Continuer avec Google'));
    await tester.pumpAndSettle();

    // Le SDK authentifie, l'API échange : c'est `auth/oauth/token` qui
    // ouvre la session, pas un code par e-mail.
    expect(oauth.asked, ['google']);
    expect(repository.calls, ['loginWithProvider:google']);
    expect(submitted, isNull);
  });

  testWidgets('reste silencieux quand l’utilisateur ferme la feuille', (
    tester,
  ) async {
    oauth = _StubOAuth(failure: OAuthFailure.cancelled);
    await pumpPage(tester);

    await tester.tap(find.text('Continuer avec Facebook'));
    await tester.pumpAndSettle();

    // Une annulation n'est pas un échec : rien à afficher, rien à
    // envoyer.
    expect(repository.calls, isEmpty);
    expect(find.textContaining('n’a pas abouti'), findsNothing);
  });

  testWidgets('dit ce qui manque quand le fournisseur n’est pas configuré', (
    tester,
  ) async {
    oauth = _StubOAuth(failure: OAuthFailure.notConfigured);
    await pumpPage(tester);

    await tester.tap(find.text('Continuer avec Google'));
    await tester.pumpAndSettle();

    expect(
      find.text(
        'Google n’est pas encore configuré sur cette version de '
        'l’app.',
      ),
      findsOneWidget,
    );
    expect(repository.calls, isEmpty);
  });

  testWidgets('explique le cas TikTok', (tester) async {
    oauth = _StubOAuth(failure: OAuthFailure.unsupported);
    await pumpPage(tester);

    await tester.tap(find.text('Continuer avec TikTok'));
    await tester.pumpAndSettle();

    expect(find.textContaining('TikTok ne remet pas'), findsOneWidget);
    expect(repository.calls, isEmpty);
  });

  testWidgets('demande le code à l’API pour une adresse valide', (
    tester,
  ) async {
    await pumpPage(tester);

    await tester.enterText(find.byType(TextField), 'passager@korabus.app');
    await tester.tap(find.text('Recevoir mon code'));
    await tester.pump();

    // Le callback seul ne prouve rien : il est branché par les tests. Ce
    // qui compte est que le parcours ait vraiment appelé l'API — sans cet
    // appel, le bouton reste sans effet sur l'appareil.
    expect(repository.calls, contains('requestOtp:registration'));
    expect(submitted, 'passager@korabus.app');
  });

  testWidgets('s’affiche en anglais quand la langue est l’anglais', (
    tester,
  ) async {
    await pumpPage(tester, locale: const Locale('en'));

    expect(find.text('Your email address'), findsOneWidget);
    expect(find.text('Continue with Google'), findsOneWidget);
    expect(find.text('or'), findsOneWidget);
  });
}

/// Les écrans du parcours lisent un [AuthFlowBloc] dans leur arbre. Le
/// dépôt bouchonné répond succès et note ce qu'on lui a demandé : c'est
/// ce qui permet de vérifier que le bouton part bien vers l'API.
Widget _withFlow(Widget child, StubAuthRepository repository) {
  return BlocProvider<AuthFlowBloc>(
    create: (_) => AuthFlowBloc(repository),
    child: child,
  );
}
