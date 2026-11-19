// Les deux écrans de l'achat groupé : l'offre (BUS-15) et la remise
// obtenue (BUS-16).

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/company_account.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/group_quote.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/itinerary.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/group_payer_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/group_quote_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/group_booking_page.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/group_discount_page.dart';

import '../../../../support/seat_hold_harness.dart';

void main() {
  setUpAll(() => AppLocalization.preloadAll());

  late Departure departure;
  late SeatHoldBloc holdBloc;

  setUp(() {
    holdBloc = SeatHoldBloc(StubReservationRepository());
    addTearDown(holdBloc.close);

    departure = mockDeparturesFor(
      SearchCriteria(
        origin: kMockCities.firstWhere((city) => city.id == 'ctn'),
        destination: kMockCities.firstWhere((city) => city.id == 'mlv'),
        date: DateTime(2026, 8, 14),
      ),
    ).first;
  });

  Widget wrap(Widget child) => MaterialApp(
    theme: AppTheme.light,
    locale: const Locale('fr'),
    supportedLocales: const [Locale('fr'), Locale('en')],
    localizationsDelegates: const [
      AppLocalization.delegate,
      GlobalMaterialLocalizations.delegate,
      GlobalWidgetsLocalizations.delegate,
      GlobalCupertinoLocalizations.delegate,
    ],
    home: child,
  );

  Future<void> pump(WidgetTester tester, Widget page) async {
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(wrap(page));
    await tester.pumpAndSettle();
  }

  /// Monte un écran du tunnel avec le devis fourni, comme le fait le
  /// `ShellRoute` dans l'app.
  Future<void> pumpInFunnel(
    WidgetTester tester,
    Widget page, {
    StubReservationRepository? repository,
    GroupPayerCubit? payer,
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 1100 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      wrap(
        withSeatHold(
          page,
          bloc: holdBloc,
          groupQuote: GroupQuoteCubit(
            repository ?? StubReservationRepository(),
          ),
          groupPayer: payer,
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  group('réservation de groupe', () {
    testWidgets('détaille le devis du serveur', (tester) async {
      // L'écran vient après le choix du départ : c'est ce qui lui permet
      // d'afficher de vrais montants plutôt que la valeur de démonstration
      // de la maquette. Barème du stub : 12 500 par voyageur, 10 % dès cinq.
      await pumpInFunnel(
        tester,
        GroupBookingPage(
          args: GroupBookingArgs(
            itinerary: Itinerary.direct(departure),
            passengers: 5,
          ),
        ),
      );

      expect(find.text('Réservation de groupe'), findsOneWidget);
      expect(find.text('Tarif initial'), findsOneWidget);
      expect(find.text(const Price(62500).formatted), findsOneWidget);
      expect(find.text('−${const Price(6250).formatted}'), findsOneWidget);
      expect(find.text('Total estimé'), findsOneWidget);
      expect(find.text(const Price(56250).formatted), findsOneWidget);
    });

    testWidgets('le compteur redemande le devis au serveur', (tester) async {
      // Le prix d'un groupe n'est pas le prix unitaire multiplié : la
      // remise dépend du seuil, et seul le serveur connaît le barème.
      final repository = StubReservationRepository();
      await pumpInFunnel(
        tester,
        GroupBookingPage(
          args: GroupBookingArgs(
            itinerary: Itinerary.direct(departure),
            passengers: 5,
          ),
        ),
        repository: repository,
      );

      await tester.tap(find.byIcon(Icons.add_rounded));
      await tester.pumpAndSettle();

      expect(repository.calls, contains('groupQuote:6'));
      expect(find.text(const Price(75000).formatted), findsOneWidget);
    });

    testWidgets('borne le compteur entre cinq et dix', (tester) async {
      await pumpInFunnel(
        tester,
        GroupBookingPage(
          args: GroupBookingArgs(
            itinerary: Itinerary.direct(departure),
            passengers: 5,
          ),
        ),
      );

      // Le plancher : un groupe sous cinq n'en est pas un.
      expect(find.text('Continuer avec 5 passagers'), findsOneWidget);
      await tester.tap(find.byIcon(Icons.remove_rounded));
      await tester.pumpAndSettle();
      expect(find.text('Continuer avec 5 passagers'), findsOneWidget);

      for (var i = 0; i < 9; i++) {
        await tester.tap(find.byIcon(Icons.add_rounded));
        await tester.pumpAndSettle();
      }
      expect(find.text('Continuer avec 10 passagers'), findsOneWidget);
    });

    testWidgets('un devis refusé laisse le tarif de la recherche', (
      tester,
    ) async {
      // Barrer l'écran parce que le devis manque coûterait plus au voyageur
      // que de lui montrer un total juste à la remise près.
      await pumpInFunnel(
        tester,
        GroupBookingPage(
          args: GroupBookingArgs(
            itinerary: Itinerary.direct(departure),
            passengers: 5,
          ),
        ),
        repository: StubReservationRepository(
          failure: const ServerFailure('Devis indisponible.'),
        ),
      );

      expect(find.text('Tarif initial'), findsOneWidget);
      expect(find.text((departure.price * 5).formatted), findsWidgets);
      expect(find.textContaining('Remise groupe'), findsNothing);
    });

    testWidgets('sans compte entreprise, aucun choix de payeur', (
      tester,
    ) async {
      // Un sélecteur à une seule option n'apprendrait rien : la plupart des
      // voyageurs n'appartiennent à aucune entreprise.
      await pumpInFunnel(
        tester,
        GroupBookingPage(
          args: GroupBookingArgs(
            itinerary: Itinerary.direct(departure),
            passengers: 5,
          ),
        ),
      );

      expect(find.text('Qui paie ?'), findsNothing);
    });

    testWidgets('un compte entreprise ne s’offre pas non plus', (tester) async {
      // Le serveur sait réserver le crédit d'une entreprise au blocage,
      // mais `POST payments/initiate` n'accepte que `mobile_money` et
      // `wallet` : le crédit réservé n'était jamais dépensé, et le
      // voyageur payait de sa poche après qu'on lui eut promis l'inverse.
      // Voir [kCompanyPayerEnabled].
      final repository = StubReservationRepository(
        accounts: const [
          CompanyAccount(
            id: 'ca-1',
            name: 'STB SARL',
            availableCredit: Price(500000),
          ),
        ],
      );

      await pumpInFunnel(
        tester,
        GroupBookingPage(
          args: GroupBookingArgs(
            itinerary: Itinerary.direct(departure),
            passengers: 5,
          ),
        ),
        payer: GroupPayerCubit(repository),
      );

      expect(find.text('Qui paie ?'), findsNothing);
      expect(find.text('STB SARL'), findsNothing);
      // Rien n'est même demandé au serveur tant que le choix est fermé.
      expect(repository.calls, isNot(contains('companyAccounts')));
    });

    test('le choix du payeur reste branché, prêt à rouvrir', () async {
      // La couche métier ne bouge pas : le jour où `initiate` accepte
      // `company_credit`, il n'y a qu'un drapeau à relever.
      final cubit = GroupPayerCubit(
        StubReservationRepository(
          accounts: const [CompanyAccount(id: 'ca-1', name: 'STB SARL')],
        ),
      );
      addTearDown(cubit.close);

      await cubit.loadAccounts();
      expect(cubit.state.payer.isCompany, isFalse);

      cubit.chooseCompany(cubit.state.accounts.single);

      expect(cubit.state.payer.companyAccountId, 'ca-1');
      expect(cubit.state.payer.toJson(), {
        'type': 'company',
        'company_account_uuid': 'ca-1',
      });
    });

    testWidgets('sans départ, l’écran ne fabrique rien', (tester) async {
      await pumpInFunnel(tester, const GroupBookingPage());

      expect(find.text('Réservation de groupe'), findsNothing);
    });
  });

  group('remise de groupe', () {
    Future<void> pumpDiscount(
      WidgetTester tester, {
      required int passengers,
      StubReservationRepository? repository,
    }) async {
      tester.view.physicalSize = const Size(390 * 3, 900 * 3);
      tester.view.devicePixelRatio = 3;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(
        wrap(
          withSeatHold(
            GroupDiscountPage(
              args: GroupDiscountArgs(
                itinerary: Itinerary.direct(departure),
                passengers: passengers,
              ),
            ),
            bloc: holdBloc,
            groupQuote: GroupQuoteCubit(
              repository ?? StubReservationRepository(),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
    }

    testWidgets('montre ce que le groupe économise', (tester) async {
      // Le barème du stub : 12 500 par voyageur, 10 % dès cinq.
      await pumpDiscount(tester, passengers: 5);

      expect(find.text('Votre remise groupe'), findsOneWidget);
      expect(find.text('Vous économisez'), findsOneWidget);
      expect(find.text(const Price(6250).formatted), findsOneWidget);
      expect(find.text('−10 % appliqué automatiquement'), findsOneWidget);
    });

    testWidgets('rappelle les trois temps du parcours', (tester) async {
      await pumpDiscount(tester, passengers: 6);

      expect(find.text('Choisir 6 sièges'), findsOneWidget);
      expect(find.text('Renseigner les 6 passagers'), findsOneWidget);
      expect(find.text('Régler en un seul paiement'), findsOneWidget);
    });

    testWidgets('sous le seuil, l’écran dit ce qu’il manque', (tester) async {
      // Le cas du staging : le serveur connaît une règle à dix, le groupe
      // n'en compte que six. L'écran d'avant affichait un tiret muet sous
      // « Vous économisez » — une promesse qu'il démentait lui-même.
      await pumpDiscount(
        tester,
        passengers: 6,
        repository: StubReservationRepository(
          groupQuoteValue: const GroupQuote(
            id: 'iq-1',
            subtotal: Price(75000),
            total: Price(75000),
            passengerCount: 6,
            discount: GroupDiscount(applied: false, threshold: 10),
          ),
        ),
      );

      expect(find.text('Bientôt une remise'), findsOneWidget);
      expect(find.text('La remise s’applique à partir de'), findsOneWidget);
      expect(find.text('10'), findsOneWidget);
      expect(
        find.text('Il vous manque 4 voyageurs pour en bénéficier.'),
        findsOneWidget,
      );
      // Le titre ne promet plus un avantage acquis.
      expect(find.text('Votre remise groupe'), findsNothing);
    });

    testWidgets('sans barème du tout, l’écran le dit', (tester) async {
      await pumpDiscount(
        tester,
        passengers: 6,
        repository: StubReservationRepository(
          groupQuoteValue: const GroupQuote(
            id: 'iq-1',
            subtotal: Price(75000),
            total: Price(75000),
            passengerCount: 6,
          ),
        ),
      );

      expect(
        find.text('KORA n’annonce aucune remise pour ce départ.'),
        findsOneWidget,
      );
      // Le parcours reste ouvert.
      expect(find.text('Choisir les sièges'), findsOneWidget);
    });

    testWidgets('sans données, l’écran ne fabrique rien', (tester) async {
      await pump(tester, const GroupDiscountPage());

      expect(find.text('Votre remise groupe'), findsNothing);
    });
  });
}
