// Réserver un voyage à correspondance — l'exemple du ticket :
// Porto-Novo → Parakou, en changeant de bus à Cotonou.
//
// Aucune ligne ne relie les deux villes ; le serveur compose le voyage,
// et l'app doit le montrer, puis demander une place dans chaque bus
// avant de n'en faire qu'une réservation.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/features/booking/data/models/itinerary_model.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/journey.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat_plan.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_seats_usecase.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seats_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/passenger_form_page.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/seats_page.dart';
import 'package:kora_app_passenger/features/booking/presentation/widgets/departure_tile.dart';
import 'package:mocktail/mocktail.dart';

import '../../../../support/bus_api_fixtures.dart';
import '../../../../support/seat_hold_harness.dart';

class _MockGetSeats extends Mock implements GetSeatsUseCase {}

class _FakeDeparture extends Fake implements Departure {}

/// Un plan de bus simple, pour que chaque tronçon ait ses propres places.
SeatPlan _planOf(String prefix) => SeatPlan.flat([
  for (var row = 1; row <= 4; row++)
    for (final column in ['A', 'B'])
      Seat(
        id: '$prefix-$row$column',
        label: '$row$column',
        row: row,
        column: column == 'A' ? 1 : 2,
      ),
]);

void main() {
  final connection = ItineraryModel.fromJson(kConnectedItinerary).toEntity()!;

  setUpAll(() async {
    await AppLocalization.preloadAll();
    registerFallbackValue(_FakeDeparture());
  });

  Widget app(Widget home) => MaterialApp(
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

  testWidgets('la carte de résultat annonce le changement de bus', (
    tester,
  ) async {
    // Sans cette ligne, le voyageur découvrirait sa correspondance en
    // descendant du premier bus.
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      app(
        Scaffold(
          body: DepartureTile(itinerary: connection, onTap: () {}),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // La carte `.connection-result-card` de la maquette : le badge doré,
    // le trajet au complet, le changement et le nombre de segments.
    expect(find.text('1 CORRESPONDANCE'), findsOneWidget);
    expect(find.text('Porto-Novo → Cotonou → Parakou'), findsOneWidget);
    expect(
      find.text('Changement de bus à Cotonou · attente ≈ 1 h'),
      findsOneWidget,
    );
    expect(find.text('2 segments inclus'), findsOneWidget);
    // Les heures et le prix sont ceux du voyage entier.
    expect(find.text('${connection.total.formatted} ›'), findsOneWidget);
  });

  testWidgets('un plan de sièges par bus, puis le formulaire', (tester) async {
    tester.view.physicalSize = const Size(390 * 3, 1000 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final holdBloc = SeatHoldBloc(StubReservationRepository());
    addTearDown(holdBloc.close);

    // Chaque tronçon a son propre plan : les places de l'un ne valent pas
    // pour l'autre.
    var call = 0;
    final getSeats = _MockGetSeats();
    when(() => getSeats(any())).thenAnswer((_) async {
      call += 1;
      return Right(_planOf(call == 1 ? 'bus1' : 'bus2'));
    });

    getIt.registerFactory<SeatsBloc>(() => SeatsBloc(getSeats));
    addTearDown(getIt.reset);

    Journey? formJourney;

    final router = GoRouter(
      routes: [
        GoRoute(
          path: '/',
          builder: (context, state) => withSeatHold(
            SeatsPage(
              journey:
                  (state.extra as SeatsArgs?)?.journey ??
                  Journey(outbound: connection),
            ),
            bloc: holdBloc,
          ),
        ),
        GoRoute(
          path: AppRouter.seatsPath,
          builder: (context, state) => withSeatHold(
            SeatsPage(journey: (state.extra! as SeatsArgs).journey),
            bloc: holdBloc,
          ),
        ),
        GoRoute(
          path: AppRouter.passengerInfoPath,
          builder: (context, state) {
            formJourney = (state.extra! as PassengerFormArgs).journey;
            return const Scaffold(body: Text('formulaire'));
          },
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      MaterialApp.router(
        theme: AppTheme.light,
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
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

    // Premier bus : le badge dit lequel, et le plan est le sien.
    // Le badge de la maquette met ses libellés en capitales.
    expect(find.text('TRAJET 1 SUR 2'), findsOneWidget);
    expect(find.text('Porto-Novo → Cotonou'), findsOneWidget);
    // Le total est celui du voyage entier : afficher le prix du tronçon
    // annoncerait 2 000 FCFA au-dessus d'un voyage facturé 12 000.
    expect(find.text(connection.total.formatted), findsOneWidget);
    await tester.tap(find.text('1A'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer'));
    await tester.pumpAndSettle();

    // Second bus : un nouveau plan, et le voyage n'est pas encore complet.
    expect(find.text('TRAJET 2 SUR 2'), findsOneWidget);
    expect(find.text('Cotonou → Parakou'), findsOneWidget);
    expect(formJourney, isNull);

    await tester.tap(find.text('2B'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer'));
    await tester.pumpAndSettle();

    // Le formulaire reçoit le voyage entier, une place par bus.
    expect(find.text('formulaire'), findsOneWidget);
    expect(formJourney!.isComplete, isTrue);
    expect(formJourney!.seatsByLeg, hasLength(2));
    expect(formJourney!.seatsOf(0).map((seat) => seat.id), [
      'bus1-1A',
      'bus2-2B',
    ]);
  });
}
