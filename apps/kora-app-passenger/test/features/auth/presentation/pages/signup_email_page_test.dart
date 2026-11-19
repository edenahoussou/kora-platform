// Tests de la première étape de création de compte : champ vide, format
// invalide, et transmission de l'adresse validée.

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_flow_bloc.dart';
import 'package:kora_app_passenger/features/auth/presentation/pages/signup_email_page.dart';
import 'package:kora_app_passenger/features/auth/presentation/widgets/phone_country_picker.dart';

import '../../../../support/stub_auth_repository.dart';

void main() {
  late String? submitted;
  late bool signedIn;
  late StubAuthRepository repository;

  // Les bouchons se posent avant chaque test, et non dans `pumpPage` :
  // plusieurs cas en remplacent un pour jouer un refus.
  setUp(() {
    submitted = null;
    signedIn = false;
    repository = StubAuthRepository();
  });

  /// Le champ de saisie d'un bloc, désigné par sa clé : le formulaire
  /// en compte plusieurs, et leur rang change dès qu'on en ajoute un.
  Finder field(String key) => find.descendant(
    of: find.byKey(Key(key)),
    matching: find.byType(TextField),
  );

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
          SyncCountryLocalizationsDelegate(),
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: _withFlow(
          SignupEmailPage(
            onSubmit: (identifier) => submitted = identifier,
            onSignIn: () => signedIn = true,
            // L'écran les masque en production ; le code, lui, reste et
            // doit continuer d'être éprouvé.
          ),
          repository,
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  /// Bascule vers l'inscription par adresse : l'écran s'ouvre désormais
  /// sur le numéro, et ces cas-là parlent de l'e-mail.
  Future<void> switchToEmail(WidgetTester tester) async {
    await tester.tap(find.text('Inscription avec votre adresse e-mail ?'));
    await tester.pumpAndSettle();
  }

  testWidgets('s’ouvre sur le numéro, champs vides', (tester) async {
    // C'est par leur numéro que les voyageurs se reconnaissent ici ; le
    // code arrive alors sur WhatsApp.
    await pumpPage(tester);

    expect(find.text('Votre numéro de téléphone'), findsOneWidget);
    expect(find.text('CRÉATION DE COMPTE'), findsOneWidget);
    expect(
      find.text('Nous vous enverrons un code de vérification sur WhatsApp.'),
      findsOneWidget,
    );

    // Les champs ne proposent rien de tout fait, juste des exemples.
    expect(find.text('🇧🇯  +229'), findsOneWidget);
    expect(find.text('01 97 00 00 00'), findsOneWidget);
    for (final champ in tester.widgetList<TextField>(find.byType(TextField))) {
      expect(champ.controller!.text, isEmpty);
    }
  });

  testWidgets('le lien mène à l’inscription par adresse', (tester) async {
    await pumpPage(tester);

    await tester.tap(find.text('Inscription avec votre adresse e-mail ?'));
    await tester.pumpAndSettle();

    expect(find.text('Votre adresse email'), findsOneWidget);
    expect(find.text('vous@exemple.com'), findsOneWidget);
    // Le nom n'est demandé que par le téléphone.
    expect(find.text('Nom complet'), findsNothing);
    expect(field('signup-email'), findsOneWidget);
  });

  testWidgets('l’inscription par téléphone demande le nom', (tester) async {
    // Le serveur répond `422 name_required` pour une inscription par
    // téléphone sans nom : il n'a aucune adresse d'où en dériver un.
    // Autant le demander ici plutôt que d'échouer après le code.
    await pumpPage(tester);

    expect(find.text('Votre numéro de téléphone'), findsOneWidget);
    expect(find.text('Nom complet'), findsOneWidget);
    expect(find.text('🇧🇯  +229'), findsOneWidget);

    await tester.enterText(field('signup-phone'), '01 97 42 18 60');
    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    expect(find.text('Nom requis'), findsOneWidget);
    expect(submitted, isNull);
  });

  testWidgets('le numéro part au format international, avec le nom', (
    tester,
  ) async {
    await pumpPage(tester);

    await tester.enterText(field('signup-name'), 'Aïcha HOUNKPE');
    await tester.enterText(field('signup-phone'), '01 97 42 18 60');
    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    expect(submitted, '+2290197421860');
    expect(repository.calls, contains('requestOtp:registration'));
  });

  testWidgets('un compte déjà créé mène à la connexion', (tester) async {
    // Le serveur répond `409 account_exists` : il n'y a rien à corriger
    // dans le formulaire, retaper la même adresse ne donnerait que la
    // même réponse. L'écran mène donc à la connexion.
    repository = StubAuthRepository(
      failure: const AccountExistsFailure(
        'Un compte existe déjà pour cet identifiant.',
      ),
    );

    await pumpPage(tester);
    await switchToEmail(tester);

    await tester.enterText(field('signup-email'), 'aicha@example.com');
    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    expect(
      find.text('Un compte existe déjà pour cet identifiant.'),
      findsOneWidget,
    );
    expect(find.text('Ce compte existe déjà.'), findsOneWidget);

    await tester.tap(find.text('Connectez-vous plutôt'));
    await tester.pumpAndSettle();

    expect(signedIn, isTrue);
  });

  testWidgets('un envoi de code impossible se lit au-dessus du bouton', (
    tester,
  ) async {
    // Le serveur répond `503 otp_delivery_unavailable` quand aucun canal
    // ne peut porter le code — un numéro sans WhatsApp, par exemple.
    //
    // Le message existait déjà, mais il était rendu SOUS le bouton, là où
    // le clavier le recouvre : le voyageur touchait « Recevoir mon code »,
    // voyait tourner, puis retrouvait le même écran sans un mot. Ce test
    // garde sa place, pas seulement sa présence.
    repository = StubAuthRepository(
      failure: const AuthFailure(
        'Impossible d’envoyer le code. Réessayez plus tard.',
      ),
    );

    await pumpPage(tester);
    await switchToEmail(tester);

    await tester.enterText(field('signup-email'), 'aicha@example.com');
    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    final message = find.text(
      'Impossible d’envoyer le code. Réessayez plus tard.',
    );
    expect(message, findsOneWidget);

    // Au-dessus, donc hors d'atteinte du clavier.
    expect(
      tester.getTopLeft(message).dy,
      lessThan(tester.getTopLeft(find.text('Recevoir mon code')).dy),
    );

    // Et le parcours n'avance pas : aucun code n'est parti.
    expect(submitted, 'aicha@example.com');
    expect(find.text('Code de vérification'), findsNothing);
  });

  testWidgets('refuse un champ vide', (tester) async {
    await pumpPage(tester);
    await switchToEmail(tester);

    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    expect(find.text('Adresse e-mail requise'), findsOneWidget);
    expect(submitted, isNull);
  });

  testWidgets('refuse une adresse mal formée', (tester) async {
    await pumpPage(tester);
    await switchToEmail(tester);

    await tester.enterText(field('signup-email'), 'aicha.example.com');
    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    expect(find.text('Adresse e-mail invalide'), findsOneWidget);
    expect(submitted, isNull);
  });

  testWidgets('transmet l’adresse validée, sans espaces superflus', (
    tester,
  ) async {
    await pumpPage(tester);
    await switchToEmail(tester);

    await tester.enterText(field('signup-email'), '  aicha@example.com  ');
    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    expect(submitted, 'aicha@example.com');
    expect(find.textContaining('invalide'), findsNothing);
  });

  testWidgets('efface l’erreur dès que la saisie reprend', (tester) async {
    await pumpPage(tester);
    await switchToEmail(tester);

    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();
    expect(find.text('Adresse e-mail requise'), findsOneWidget);

    await tester.enterText(field('signup-email'), 'a');
    await tester.pumpAndSettle();
    expect(find.text('Adresse e-mail requise'), findsNothing);
  });

  testWidgets('demande le code à l’API pour une adresse valide', (
    tester,
  ) async {
    await pumpPage(tester);
    await switchToEmail(tester);

    await tester.enterText(field('signup-email'), 'passager@korabus.app');
    await tester.tap(find.text('Recevoir mon code'));
    await tester.pump();

    // Le callback seul ne prouve rien : il est branché par les tests. Ce
    // qui compte est que le parcours ait vraiment appelé l'API — sans cet
    // appel, le bouton reste sans effet sur l'appareil.
    expect(repository.calls, contains('requestOtp:registration'));
    expect(submitted, 'passager@korabus.app');
  });

  /// Le parcours que l'écran alimente, lu dans son arbre.
  AuthFlowBloc flowOf(WidgetTester tester) => BlocProvider.of<AuthFlowBloc>(
    tester.element(find.byType(SignupEmailPage)),
  );

  group('code de parrainage', () {
    testWidgets('se saisit ici, après l’identifiant', (tester) async {
      // Le voyageur qui a reçu un code l'a sous les yeux au moment où
      // il se présente. Le demander trois écrans plus loin, au choix du
      // PIN, l'obligeait à le retrouver.
      await pumpPage(tester);

      expect(find.text('Code de parrainage (facultatif)'), findsOneWidget);
      expect(field('signup-referral'), findsOneWidget);
    });

    testWidgets('part avec la demande de code, en majuscules', (tester) async {
      // Le serveur compare des majuscules : une frappe en minuscules ne
      // doit pas valoir « code inconnu ».
      await pumpPage(tester);

      await tester.enterText(field('signup-name'), 'Aïcha HOUNKPE');
      await tester.enterText(field('signup-phone'), '01 97 42 18 60');
      await tester.enterText(field('signup-referral'), 'ka1b2c3d');
      await tester.tap(find.text('Recevoir mon code'));
      await tester.pumpAndSettle();

      // Gardé par le parcours jusqu'à la création du compte, deux
      // écrans plus loin : c'est elle qui rattache le parrain.
      expect(flowOf(tester).state.registrationReferralCode, 'KA1B2C3D');
    });

    testWidgets('laissé vide, rien ne part', (tester) async {
      // Une chaîne vide ferait refuser l'activation d'un compte
      // préexistant sans PIN.
      await pumpPage(tester);

      await tester.enterText(field('signup-name'), 'Aïcha HOUNKPE');
      await tester.enterText(field('signup-phone'), '01 97 42 18 60');
      await tester.tap(find.text('Recevoir mon code'));
      await tester.pumpAndSettle();

      expect(flowOf(tester).state.registrationReferralCode, isEmpty);
    });
  });

  testWidgets('s’affiche en anglais quand la langue est l’anglais', (
    tester,
  ) async {
    await pumpPage(tester, locale: const Locale('en'));

    expect(find.text('Your phone number'), findsOneWidget);
    expect(find.text('Full name'), findsOneWidget);

    await tester.tap(find.text('Sign up with your email address?'));
    await tester.pumpAndSettle();
    expect(find.text('Your email address'), findsOneWidget);
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
