// Le parcours d'envoi d'un colis — écrans 02 à 06 de la maquette.
//
// Ce que le ticket exige et que ces tests tiennent : l'itinéraire se
// choisit avant le contenu, on peut revenir corriger sans rien perdre,
// le poids déclaré ne se confond jamais avec la pesée, et aucune
// référence officielle n'existe avant le paiement.

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/features/colis/data/datasources/colis_network_mock.dart';
import 'package:kora_app_passenger/features/colis/presentation/bloc/colis_draft_cubit.dart';
import 'package:kora_app_passenger/features/colis/presentation/pages/colis_contacts_page.dart';
import 'package:kora_app_passenger/features/colis/presentation/pages/colis_content_page.dart';
import 'package:kora_app_passenger/features/colis/presentation/pages/colis_preregistered_page.dart';
import 'package:kora_app_passenger/features/colis/presentation/pages/colis_route_page.dart';
import 'package:kora_app_passenger/features/colis/presentation/pages/colis_summary_page.dart';
import 'package:kora_app_passenger/features/home/presentation/pages/city_picker_page.dart';

/// Le sélecteur de villes, monté comme le routeur de l'app le monte.
Widget _picker(GoRouterState state, CityField field) {
  final args = state.extra! as CityPickerArgs;

  return CityPickerPage(
    field: field,
    cities: args.cities,
    recentCities: args.recentCities,
    selected: args.selected,
    purpose: args.purpose,
  );
}

void main() {
  late ColisDraftCubit draft;

  setUpAll(AppLocalization.preloadAll);

  setUp(() {
    draft = ColisDraftCubit()
      ..start(senderName: 'Koffi MENSAH', senderPhone: '+229 97 12 34 45');
  });

  tearDown(() => draft.close());

  Widget wrap(Widget child) =>
      BlocProvider<ColisDraftCubit>.value(value: draft, child: child);

  Future<GoRouter> pumpFlow(WidgetTester tester) async {
    // L'étape 2 s'est allongée — liste d'interdits, certification,
    // réassurance : la fenêtre du test doit les contenir pour qu'on
    // puisse les toucher.
    tester.view.physicalSize = const Size(390 * 3, 3200 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final router = GoRouter(
      initialLocation: AppRouter.colisRoutePath,
      routes: [
        GoRoute(
          path: AppRouter.colisRoutePath,
          builder: (context, state) => wrap(const ColisRoutePage()),
        ),
        GoRoute(
          path: AppRouter.colisContentPath,
          builder: (context, state) => wrap(const ColisContentPage()),
        ),
        GoRoute(
          path: AppRouter.colisContactsPath,
          builder: (context, state) => wrap(const ColisContactsPage()),
        ),
        GoRoute(
          path: AppRouter.colisSummaryPath,
          builder: (context, state) => wrap(const ColisSummaryPage()),
        ),
        GoRoute(
          path: AppRouter.colisPreregisteredPath,
          builder: (context, state) => wrap(const ColisPreregisteredPage()),
        ),
        GoRoute(
          path: AppRouter.originPath,
          builder: (context, state) => _picker(state, CityField.origin),
        ),
        GoRoute(
          path: AppRouter.destinationPath,
          builder: (context, state) => _picker(state, CityField.destination),
        ),
        GoRoute(
          path: '/colis',
          builder: (context, state) => const Scaffold(body: Text('accueil')),
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      MaterialApp.router(
        theme: AppTheme.light,
        locale: const Locale('fr', 'FR'),
        supportedLocales: const [Locale('fr', 'FR')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        routerConfig: router,
      ),
    );
    await tester.pumpAndSettle();
    return router;
  }

  /// Renseigne un emplacement : le champ ouvre le sélecteur de villes,
  /// et la feuille tranche quand la ville compte plusieurs guichets.
  Future<void> chooseHub(
    WidgetTester tester, {
    required String slot,
    required String city,
    String? hub,
  }) async {
    await tester.tap(find.text(slot));
    await tester.pumpAndSettle();

    await tester.tap(find.text(city).last);
    await tester.pumpAndSettle();

    if (hub == null) return;
    await tester.tap(find.text(hub).last);
    await tester.pumpAndSettle();
  }

  /// Compose Cotonou (Gare Centrale) → Parakou (Hub Gare Nord) sur
  /// l'écran d'itinéraire.
  Future<void> chooseRoute(WidgetTester tester) async {
    await chooseHub(
      tester,
      slot: 'VILLE ET HUB DE DÉPÔT',
      city: 'Cotonou',
      hub: 'Cotonou · Gare Centrale',
    );
    await chooseHub(tester, slot: 'VILLE ET HUB DE RETRAIT', city: 'Parakou');
  }

  testWidgets('l’itinéraire se choisit avant le contenu', (tester) async {
    await pumpFlow(tester);

    // Rien n'est composé : continuer n'a pas de sens, et la maquette le
    // dit en grisant l'action.
    final cta = find.widgetWithText(
      KoraButton,
      'Continuer vers les informations du colis',
    );
    expect(tester.widget<KoraButton>(cta).onPressed, isNull);

    await chooseRoute(tester);

    expect(tester.widget<KoraButton>(cta).onPressed, isNotNull);
    await tester.tap(cta);
    await tester.pumpAndSettle();

    expect(find.text('Que contient votre colis ?'), findsOneWidget);
    // L'itinéraire reste sous les yeux à l'étape suivante.
    expect(find.textContaining('Cotonou (Gare Centrale)'), findsOneWidget);
  });

  testWidgets('le sélecteur parle colis, pas voyage', (tester) async {
    // Le même écran sert les deux parcours. Ouvert depuis un envoi, il
    // demandait pourtant « d'où partez-vous ? » : la question du bus,
    // posée à quelqu'un qui ne monte pas dans le car.
    await pumpFlow(tester);

    await tester.tap(find.text('VILLE ET HUB DE DÉPÔT'));
    await tester.pumpAndSettle();
    expect(find.text('D’où part le colis ?'), findsOneWidget);
    expect(find.text('D’où partez-vous ?'), findsNothing);
    // Et la ligne annonce un guichet, pas un arrêt de car.
    expect(find.text('Guichet colis KORA'), findsWidgets);
    expect(find.text('Arrêt KORA'), findsNothing);

    await tester.tap(find.text('Cotonou').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cotonou · Gare Centrale').last);
    await tester.pumpAndSettle();

    await tester.tap(find.text('VILLE ET HUB DE RETRAIT'));
    await tester.pumpAndSettle();
    expect(find.text('Où le retirer ?'), findsOneWidget);
    expect(find.text('Où allez-vous ?'), findsNothing);
  });

  testWidgets('une liaison non desservie ne laisse pas continuer', (
    tester,
  ) async {
    // Deux hubs ne font pas un corridor : l'ADR 0028 refuse tout
    // transfert implicite, et l'écran doit le dire plutôt que de laisser
    // le voyageur se présenter au guichet.
    await pumpFlow(tester);

    await chooseHub(tester, slot: 'VILLE ET HUB DE DÉPÔT', city: 'Porto-Novo');
    await chooseHub(tester, slot: 'VILLE ET HUB DE RETRAIT', city: 'Djougou');

    expect(find.text('Liaison non desservie'), findsOneWidget);
    expect(
      tester
          .widget<KoraButton>(
            find.widgetWithText(
              KoraButton,
              'Continuer vers les informations du colis',
            ),
          )
          .onPressed,
      isNull,
    );
  });

  testWidgets('le poids déclaré se règle au demi-kilo et reste déclaré', (
    tester,
  ) async {
    await pumpFlow(tester);
    await chooseRoute(tester);
    await tester.tap(find.text('Continuer vers les informations du colis'));
    await tester.pumpAndSettle();

    expect(find.text('3'), findsOneWidget);
    await tester.tap(find.byIcon(Icons.add_rounded));
    await tester.pumpAndSettle();
    expect(find.text('3,5'), findsOneWidget);

    // Le cœur du ticket : rien n'a été pesé, donc rien n'est facturable
    // de façon ferme.
    expect(draft.state.declaredWeightKg, 3.5);
    expect(draft.state.certifiedWeightKg, isNull);
    expect(draft.state.billableWeightKg, 3.5);
    expect(find.text('Pesée certifiée au hub'), findsOneWidget);
  });

  testWidgets('le récapitulatif annonce une fourchette, jamais un prix', (
    tester,
  ) async {
    draft
      ..chooseOrigin('ctn-gare-centrale')
      ..chooseDestination('pkx-gare-nord')
      ..describe('Documents administratifs et fournitures de bureau')
      ..certify(true)
      ..setRecipient(name: 'Amina BIO', phone: '95000088');

    await pumpFlow(tester);
    await tester.tap(find.text('Continuer vers les informations du colis'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer vers les coordonnées'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer vers le récapitulatif'));
    await tester.pumpAndSettle();

    expect(find.text('Vérifiez avant le dépôt'), findsOneWidget);
    expect(find.text('Tarif indicatif'), findsOneWidget);
    expect(find.textContaining('Entre '), findsOneWidget);
    expect(find.textContaining('selon la pesée au hub'), findsOneWidget);
    // Chaque bloc ramène à son étape : on relit, donc on se corrige.
    expect(find.text('Modifier'), findsNWidgets(3));
  });

  testWidgets('corriger depuis le récapitulatif y ramène directement', (
    tester,
  ) async {
    // Le ticket demande qu'on puisse revenir corriger une étape sans
    // refaire le parcours. « Modifier » y menait déjà ; c'est le retour
    // qui manquait — « Continuer » repartait vers l'étape suivante, et
    // il fallait retraverser les trois écrans pour un nom mal saisi.
    draft
      ..chooseOrigin('ctn-gare-centrale')
      ..chooseDestination('pkx-gare-nord')
      ..describe('Documents administratifs')
      ..certify(true)
      ..setRecipient(name: 'Amina BIO', phone: '95000088');

    await pumpFlow(tester);
    await tester.tap(find.text('Continuer vers les informations du colis'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer vers les coordonnées'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer vers le récapitulatif'));
    await tester.pumpAndSettle();

    // Le bloc des intervenants est le troisième du récapitulatif.
    await tester.tap(find.text('Modifier').at(2));
    await tester.pumpAndSettle();
    expect(find.text('Qui envoie et qui reçoit ?'), findsOneWidget);
    expect(draft.state.editing, isTrue);

    await tester.tap(find.text('Continuer vers le récapitulatif'));
    await tester.pumpAndSettle();

    expect(find.text('Vérifiez avant le dépôt'), findsOneWidget);
    // Le drapeau se range : l'étape redevient une étape.
    expect(draft.state.editing, isFalse);
  });

  testWidgets('deux mots suffisent à décrire le contenu', (tester) async {
    // Le seuil était de dix caractères : « 2 pagnes » ne l'atteignait
    // pas et le bouton restait gris sans dire pourquoi.
    await pumpFlow(tester);
    await chooseRoute(tester);
    await tester.tap(find.text('Continuer vers les informations du colis'));
    await tester.pumpAndSettle();

    final cta = find.widgetWithText(
      KoraButton,
      'Continuer vers les coordonnées',
    );
    expect(tester.widget<KoraButton>(cta).onPressed, isNull);

    await tester.enterText(find.byType(TextField).first, '2 pagnes');
    await tester.pumpAndSettle();
    // La description suffit, mais la certification sur l'honneur reste
    // due : l'agent ouvrira le colis au guichet.
    expect(tester.widget<KoraButton>(cta).onPressed, isNull);

    // La case n'est plus un `Checkbox` Material : la maquette la veut
    // carrée, grise puis marine. On touche donc son libellé, qui est
    // la cible réelle du geste.
    // `findRichText` : le libellé porte son astérisque rouge dans un
    // `RichText`, que le finder ignore sans ce drapeau.
    await tester.tap(
      find.textContaining('Je certifie sur l’honneur', findRichText: true),
    );
    await tester.pumpAndSettle();

    expect(tester.widget<KoraButton>(cta).onPressed, isNotNull);
  });

  testWidgets('le pré-enregistrement n’a pas de référence officielle', (
    tester,
  ) async {
    draft
      ..chooseOrigin('ctn-gare-centrale')
      ..chooseDestination('pkx-gare-nord')
      ..describe('Documents administratifs et fournitures de bureau')
      ..certify(true)
      ..setRecipient(name: 'Amina BIO', phone: '95000088')
      ..preregister();

    await pumpFlow(tester);
    await tester.tap(find.text('Continuer vers les informations du colis'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer vers les coordonnées'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer vers le récapitulatif'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Pré-enregistrer l’envoi'));
    await tester.pumpAndSettle();

    expect(find.text('Pré-enregistrement validé'), findsOneWidget);
    expect(
      find.text('Aucune référence officielle pour l’instant'),
      findsOneWidget,
    );
    // Le dossier existe côté escale, la référence non.
    expect(draft.state.stage, ColisDraftStage.preregistered);
    expect(draft.state.reference, isNull);
    // L'écran parle bien de « KC-… », mais comme d'une promesse : aucun
    // numéro réel ne doit s'afficher avant le paiement.
    expect(find.textContaining(RegExp(r'KC-\d')), findsNothing);
  });

  group('tarif', () {
    test('la tranche de poids se compte entamée', () {
      // 5,1 kg paie la même chose que 10 kg : c'est la règle du runbook,
      // et arrondir vers le bas ferait encaisser moins que le tarif
      // publié.
      int fee(double kg) => colisTransportFee(
        originCity: 'Cotonou',
        destinationCity: 'Parakou',
        weightKg: kg,
      );

      expect(fee(3), 1500);
      expect(fee(5), 1500);
      expect(fee(5.5), 3000);
      expect(fee(10), 3000);
      expect(fee(10.5), 4500);
    });

    test('un corridor absent de la grille n’a pas de prix', () {
      expect(colisCorridorExists('Porto-Novo', 'Djougou'), isFalse);
      expect(
        colisTransportFee(
          originCity: 'Porto-Novo',
          destinationCity: 'Djougou',
          weightKg: 3,
        ),
        0,
      );
    });

    test('la pesée du hub remplace la déclaration', () {
      const declared = ColisDraft(
        originHubId: 'ctn-gare-centrale',
        destinationHubId: 'pkx-gare-nord',
        declaredWeightKg: 4.5,
      );
      final weighed = declared.copyWith(certifiedWeightKg: 5.5);

      expect(declared.billableWeightKg, 4.5);
      expect(declared.total, 1500 + kColisSealFee);

      expect(weighed.billableWeightKg, 5.5);
      expect(weighed.weightGapKg, closeTo(1, 0.001));
      expect(weighed.total, 3000 + kColisSealFee);
    });
  });
}
