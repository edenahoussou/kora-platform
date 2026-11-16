// Tests de l'écran de sélection de siège : cohérence du plan avec les
// places annoncées, sélection/désélection, quota et total.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/itinerary.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/journey.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat_plan.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_seats_usecase.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/group_quote_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seats_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/seats_page.dart';
import 'package:mocktail/mocktail.dart';

import '../../../../support/seat_hold_harness.dart';

class MockGetSeatsUseCase extends Mock implements GetSeatsUseCase {}

class _FakeDeparture extends Fake implements Departure {}

void main() {
  late SeatHoldBloc holdBloc;

  setUpAll(() => AppLocalization.preloadAll());

  final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
  final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');

  late MockGetSeatsUseCase getSeats;
  late Departure departure;
  late List<Seat> seats;

  setUpAll(() => registerFallbackValue(_FakeDeparture()));

  setUp(() {
    holdBloc = SeatHoldBloc(StubReservationRepository());
    addTearDown(holdBloc.close);

    departure = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: malanville,
        date: DateTime(2026, 8, 14),
      ),
    ).first;
    seats = mockSeatsFor(departure);

    getSeats = MockGetSeatsUseCase();
    when(
      () => getSeats(any()),
    ).thenAnswer((_) async => Right(SeatPlan.flat(seats)));

    getIt.registerFactory<SeatsBloc>(() => SeatsBloc(getSeats));
  });

  tearDown(() => getIt.reset());

  Future<void> pumpSeats(
    WidgetTester tester, {
    int passengers = 1,
    GroupQuoteCubit? groupQuote,
  }) async {
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
        home: withSeatHold(
          SeatsPage(
            journey: Journey(outbound: Itinerary.direct(departure)),
            passengers: passengers,
          ),
          bloc: holdBloc,
          groupQuote: groupQuote,
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  group('plan mocké', () {
    test('laisse exactement le nombre de places annoncé par le départ', () {
      expect(departure.seatsAvailable, 12);
      expect(seats.where((seat) => seat.status == SeatStatus.free).length, 12);
    });

    test('compte les 55 places du bus et condamne la première rangée', () {
      expect(seats.length, 55);
      expect(departure.seatsTotal, 55);

      final firstRow = seats.where((seat) => seat.row == 1);
      expect(firstRow, hasLength(4));
      expect(
        firstRow.every((seat) => seat.status == SeatStatus.blocked),
        isTrue,
      );
    });

    test('redonne le même plan pour le même départ', () {
      final again = mockSeatsFor(departure);
      expect(
        again.map((seat) => seat.status),
        seats.map((seat) => seat.status),
      );
    });
  });

  group('le plan de cabine du serveur', () {
    /// Une cabine minuscule, mais complète : un poste de conduite, une
    /// porte, un couloir, et quatre sièges dont un d'équipage et un hors
    /// service.
    ///
    /// Elle n'est pas en 2+2 — c'est tout l'intérêt : c'est le cas que
    /// l'ancien plan, qui déduisait la position du code, dessinait de
    /// travers.
    const grid = SeatGrid(
      rows: 3,
      cols: 3,
      cells: [
        SeatCell(kind: SeatCellKind.driver, x: 0, y: 0),
        SeatCell(kind: SeatCellKind.door, x: 2, y: 0),
        SeatCell(kind: SeatCellKind.seat, x: 0, y: 1, code: '1A'),
        SeatCell(kind: SeatCellKind.aisle, x: 1, y: 1),
        SeatCell(kind: SeatCellKind.seat, x: 2, y: 1, code: '1B'),
        SeatCell(kind: SeatCellKind.seat, x: 0, y: 2, code: '2A', isCrew: true),
        SeatCell(
          kind: SeatCellKind.seat,
          x: 2,
          y: 2,
          code: '2B',
          isUnderMaintenance: true,
        ),
      ],
    );

    const inventory = [
      Seat(id: 's1', label: '1A', row: 1, column: 1),
      Seat(id: 's2', label: '1B', row: 1, column: 2),
      Seat(id: 's3', label: '2A', row: 2, column: 1),
      Seat(id: 's4', label: '2B', row: 2, column: 2),
    ];

    setUp(() {
      when(() => getSeats(any())).thenAnswer(
        (_) async => const Right(
          SeatPlan(seats: inventory, grid: grid, compositionId: 'comp-1'),
        ),
      );
    });

    testWidgets('dessine les sièges de la grille, et eux seuls', (
      tester,
    ) async {
      await pumpSeats(tester);

      for (final code in const ['1A', '1B', '2A', '2B']) {
        expect(find.text(code), findsOneWidget, reason: code);
      }

      // Le couloir, la porte et le poste de conduite occupent leur case
      // sans porter de siège : rien à compter de plus.
      expect(find.byType(KoraSeat), findsNothing);
    });

    testWidgets('rend intouchables l’équipage et le hors-service', (
      tester,
    ) async {
      // Les deux places existent à bord et se voient sur le plan ; les
      // offrir à la vente mènerait le voyageur à un refus du serveur.
      await pumpSeats(tester);

      await tester.tap(find.text('2A'));
      await tester.tap(find.text('2B'));
      await tester.pumpAndSettle();

      expect(find.text('Aucun siège · 0/1'), findsOneWidget);
    });

    testWidgets('laisse choisir un siège vendable', (tester) async {
      await pumpSeats(tester);

      await tester.tap(find.text('1B'));
      await tester.pumpAndSettle();

      expect(find.text('1B · 1/1'), findsOneWidget);
    });
  });

  testWidgets('affiche l’en-tête et la barre de validation', (tester) async {
    await pumpSeats(tester);

    expect(find.text('Choisissez votre siège'), findsOneWidget);
    // Le véhicule réel quand l'API le donne ; ici un départ mocké, qui
    // n'a que sa gamme — d'où le repli sur celle-ci.
    expect(find.text('KORA CONFORT · 55 PLACES'), findsOneWidget);
    expect(find.text('Aucun siège · 0/1'), findsOneWidget);
    expect(find.byType(SeatMap), findsOneWidget);
  });

  testWidgets('sélectionne puis désélectionne un siège libre', (tester) async {
    await pumpSeats(tester);

    final free = seats.firstWhere((seat) => seat.status == SeatStatus.free);

    await tester.tap(find.text(free.label));
    await tester.pumpAndSettle();
    expect(find.text('${free.label} · 1/1'), findsOneWidget);

    await tester.tap(find.text(free.label));
    await tester.pumpAndSettle();
    expect(find.text('Aucun siège · 0/1'), findsOneWidget);
  });

  testWidgets('ignore un siège déjà vendu', (tester) async {
    await pumpSeats(tester);

    final sold = seats.firstWhere((seat) => seat.status == SeatStatus.occupied);

    await tester.tap(find.text(sold.label));
    await tester.pumpAndSettle();

    expect(find.text('Aucun siège · 0/1'), findsOneWidget);
  });

  testWidgets('remplace la sélection au-delà du quota de voyageurs', (
    tester,
  ) async {
    await pumpSeats(tester);

    final free = seats
        .where((seat) => seat.status == SeatStatus.free)
        .take(2)
        .toList();

    await tester.tap(find.text(free[0].label));
    await tester.pumpAndSettle();
    await tester.tap(find.text(free[1].label));
    await tester.pumpAndSettle();

    // Un seul voyageur : le second siège remplace le premier.
    expect(find.text('${free[1].label} · 1/1'), findsOneWidget);
  });

  testWidgets('additionne le total pour deux voyageurs', (tester) async {
    await pumpSeats(tester, passengers: 2);

    expect(find.text('Aucun siège · 0/2'), findsOneWidget);

    final free = seats
        .where((seat) => seat.status == SeatStatus.free)
        .take(2)
        .toList();

    await tester.tap(find.text(free[0].label));
    await tester.pumpAndSettle();
    await tester.tap(find.text(free[1].label));
    await tester.pumpAndSettle();

    expect(
      find.text('${free[0].label}, ${free[1].label} · 2/2'),
      findsOneWidget,
    );
    expect(find.text((departure.price * 2).formatted), findsOneWidget);
  });

  group('siège refusé par le serveur', () {
    test('repeint le siège en occupé et le sort de la sélection', () async {
      final bloc = SeatsBloc(getSeats);
      addTearDown(bloc.close);

      bloc.add(SeatsRequested(departure: departure));
      await bloc.stream.firstWhere(
        (state) => state.status == SeatsStatus.success,
      );

      final chosen = bloc.state.seats.firstWhere((seat) => seat.isSelectable);
      bloc.add(SeatToggled(chosen));
      await bloc.stream.firstWhere((state) => state.selectedSeats.isNotEmpty);

      // Le serveur nomme le siège qu'il vient d'attribuer à un autre.
      bloc.add(SeatRefused(seatId: chosen.id, seatLabel: chosen.label));
      await bloc.stream.firstWhere((state) => state.selectedSeats.isEmpty);

      final refused = bloc.state.seats.firstWhere(
        (seat) => seat.id == chosen.id,
      );

      // Le plan dit la vérité, et « Continuer » redevient hors d'atteinte
      // tant qu'un autre siège n'est pas choisi.
      expect(refused.status, SeatStatus.occupied);
      expect(refused.isSelectable, isFalse);
      expect(bloc.state.canContinue, isFalse);
    });

    test('sans siège nommé, vide simplement la sélection', () async {
      final bloc = SeatsBloc(getSeats);
      addTearDown(bloc.close);

      bloc.add(SeatsRequested(departure: departure));
      await bloc.stream.firstWhere(
        (state) => state.status == SeatsStatus.success,
      );

      bloc.add(
        SeatToggled(bloc.state.seats.firstWhere((seat) => seat.isSelectable)),
      );
      await bloc.stream.firstWhere((state) => state.selectedSeats.isNotEmpty);

      // Un 409 qui ne nomme personne : mieux vaut faire rechoisir que
      // laisser croire qu'un siège est encore libre.
      bloc.add(const SeatRefused());
      await bloc.stream.firstWhere((state) => state.selectedSeats.isEmpty);

      expect(bloc.state.canContinue, isFalse);
    });
  });

  testWidgets('un groupe voit le total du devis, remise comprise', (
    tester,
  ) async {
    // Deux écrans annoncent la remise avant celui-ci. Y réafficher le
    // tarif plein revient à la reprendre sans le dire : sur la staging le
    // voyageur lisait 284 400, puis 316 000 sous le plan.
    final quote = GroupQuoteCubit(StubReservationRepository());
    addTearDown(quote.close);
    await quote.load(
      journey: Journey(outbound: Itinerary.direct(departure)),
      passengers: 5,
    );

    await pumpSeats(tester, passengers: 5, groupQuote: quote);

    // Barème du stub : 12 500 par voyageur, 10 % dès cinq.
    expect(find.text(const Price(56250).formatted), findsOneWidget);
    expect(find.text((departure.price * 5).formatted), findsNothing);
  });

  testWidgets('sans devis, le total reste celui de la recherche', (
    tester,
  ) async {
    // Un devis refusé ne doit pas vider le total : le montant est juste à
    // la remise près, et c'est mieux qu'un écran barré.
    await pumpSeats(tester, passengers: 5);

    final free = seats
        .where((seat) => seat.status == SeatStatus.free)
        .take(5)
        .toList();
    for (final seat in free) {
      await tester.tap(find.text(seat.label));
      await tester.pumpAndSettle();
    }

    expect(find.text((departure.price * 5).formatted), findsOneWidget);
  });
}
