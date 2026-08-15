// Tests de l'écran de sélection de siège : cohérence du plan avec les
// places annoncées, sélection/désélection, quota et total.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_seats_usecase.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seats_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/seats_page.dart';
import 'package:mocktail/mocktail.dart';

class MockGetSeatsUseCase extends Mock implements GetSeatsUseCase {}

class _FakeDeparture extends Fake implements Departure {}

void main() {
  final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
  final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');

  late MockGetSeatsUseCase getSeats;
  late Departure departure;
  late List<Seat> seats;

  setUpAll(() => registerFallbackValue(_FakeDeparture()));

  setUp(() {
    departure = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: malanville,
        date: DateTime(2026, 8, 14),
      ),
    ).first;
    seats = mockSeatsFor(departure);

    getSeats = MockGetSeatsUseCase();
    when(() => getSeats(any())).thenAnswer((_) async => Right(seats));

    getIt.registerFactory<SeatsBloc>(() => SeatsBloc(getSeats));
  });

  tearDown(() => getIt.reset());

  Future<void> pumpSeats(WidgetTester tester, {int passengers = 1}) async {
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: SeatsPage(departure: departure, passengers: passengers),
      ),
    );
    await tester.pumpAndSettle();
  }

  group('plan mocké', () {
    test('laisse exactement le nombre de places annoncé par le départ', () {
      expect(departure.seatsAvailable, 12);
      expect(
        seats.where((seat) => seat.status == SeatStatus.free).length,
        12,
      );
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
      expect(again.map((seat) => seat.status), seats.map((seat) => seat.status));
    });
  });

  testWidgets('affiche l’en-tête et la barre de validation', (tester) async {
    await pumpSeats(tester);

    expect(find.text('Choisissez votre siège'), findsOneWidget);
    expect(find.text('KORA CONFORT · BUS 55 PLACES'), findsOneWidget);
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

    final sold = seats.firstWhere(
      (seat) => seat.status == SeatStatus.occupied,
    );

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
}
