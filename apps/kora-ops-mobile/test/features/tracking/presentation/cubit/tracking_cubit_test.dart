import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:kora_ops_mobile/features/ops/data/datasources/selected_departure_local_datasource.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/depart.dart';
import 'package:kora_ops_mobile/features/ops/presentation/cubit/selected_depart_cubit.dart';
import 'package:kora_ops_mobile/features/terminal/data/datasources/terminal_local_datasource.dart';
import 'package:kora_ops_mobile/features/tracking/domain/entities/tracking_availability.dart';
import 'package:kora_ops_mobile/features/tracking/domain/entities/tracking_runtime_status.dart';
import 'package:kora_ops_mobile/features/tracking/domain/entities/tracking_stop_reason.dart';
import 'package:kora_ops_mobile/features/tracking/domain/repositories/tracking_repository.dart';
import 'package:kora_ops_mobile/features/tracking/presentation/cubit/tracking_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockTrackingRepository extends Mock implements TrackingRepository {}

class MockTerminalLocalDataSource extends Mock
    implements TerminalLocalDataSource {}

class MockSelectedDepartureLocalDataSource extends Mock
    implements SelectedDepartureLocalDataSource {}

class MockAuthCubit extends Mock implements AuthCubit {}

void main() {
  setUpAll(() {
    registerFallbackValue(TrackingStopReason.manualClose);
  });

  late MockTrackingRepository repository;
  late MockTerminalLocalDataSource terminalLocalDataSource;
  late SelectedDepartCubit selectedDepartCubit;
  late StreamController<TrackingRuntimeStatus> statusController;

  const String departureUuid = 'dep-uuid-1';
  const String terminalSerial = 'P501-0042';

  const Depart departed = Depart(
    id: departureUuid,
    route: 'Cotonou → Porto-Novo',
    date: '22/09/2026',
    heure: '08:00',
    vehiculeImmat: 'AB-1234-RB',
    totalPlaces: 45,
    placesOccupees: 30,
    status: 'departed',
  );

  const Depart arrived = Depart(
    id: departureUuid,
    route: 'Cotonou → Porto-Novo',
    date: '22/09/2026',
    heure: '08:00',
    vehiculeImmat: 'AB-1234-RB',
    totalPlaces: 45,
    placesOccupees: 30,
    status: 'arrived',
  );

  const Depart cancelled = Depart(
    id: departureUuid,
    route: 'Cotonou → Porto-Novo',
    date: '22/09/2026',
    heure: '08:00',
    vehiculeImmat: 'AB-1234-RB',
    totalPlaces: 45,
    placesOccupees: 30,
    status: 'cancelled',
  );

  const Depart boarding = Depart(
    id: departureUuid,
    route: 'Cotonou → Porto-Novo',
    date: '22/09/2026',
    heure: '08:00',
    vehiculeImmat: 'AB-1234-RB',
    totalPlaces: 45,
    placesOccupees: 30,
    status: 'boarding',
  );

  setUp(() {
    repository = MockTrackingRepository();
    terminalLocalDataSource = MockTerminalLocalDataSource();
    statusController = StreamController<TrackingRuntimeStatus>.broadcast();

    when(() => repository.statusStream)
        .thenAnswer((_) => statusController.stream);
    when(() => terminalLocalDataSource.readSerialNumber())
        .thenAnswer((_) async => terminalSerial);
    when(() => repository.start(
          departureUuid: any(named: 'departureUuid'),
          terminalSerial: any(named: 'terminalSerial'),
        )).thenAnswer((_) async => TrackingAvailability.ready);
    when(() => repository.stop(reason: any(named: 'reason')))
        .thenAnswer((_) async {});
    when(() => repository.bootstrap()).thenAnswer((_) async {});
    when(() => repository.checkAvailability())
        .thenAnswer((_) async => TrackingAvailability.ready);
    when(() => repository.requestAvailability())
        .thenAnswer((_) async => TrackingAvailability.ready);
    when(() => repository.totalPendingCount()).thenAnswer((_) async => 0);

    final MockSelectedDepartureLocalDataSource local =
        MockSelectedDepartureLocalDataSource();
    when(() => local.saveSnapshot(any())).thenAnswer((_) async {});
    when(local.clear).thenAnswer((_) async {});
    when(local.readSnapshot).thenAnswer((_) async => null);
    final MockAuthCubit authCubit = MockAuthCubit();
    when(() => authCubit.stream).thenAnswer((_) => const Stream.empty());
    selectedDepartCubit = SelectedDepartCubit(local, authCubit);
  });

  tearDown(() async {
    await statusController.close();
    await selectedDepartCubit.close();
  });

  TrackingCubit buildCubit() =>
      TrackingCubit(repository, terminalLocalDataSource, selectedDepartCubit);

  group('transitions du départ sélectionné', () {
    test('démarre le suivi quand le départ passe à departed', () async {
      final TrackingCubit cubit = buildCubit();
      addTearDown(cubit.close);

      await selectedDepartCubit.select(departed);
      await Future<void>.delayed(Duration.zero);

      verify(() => repository.start(
            departureUuid: departureUuid,
            terminalSerial: terminalSerial,
          )).called(1);
      expect(cubit.state.isActive, isTrue);
    });

    test('arrête le suivi quand le départ passe à arrived', () async {
      final TrackingCubit cubit = buildCubit();
      addTearDown(cubit.close);

      await selectedDepartCubit.select(departed);
      await Future<void>.delayed(Duration.zero);
      await selectedDepartCubit.select(arrived);
      await Future<void>.delayed(Duration.zero);

      verify(() => repository.stop(reason: TrackingStopReason.arrived))
          .called(1);
      expect(cubit.state.isActive, isFalse);
    });

    test('arrête le suivi quand le départ passe à cancelled', () async {
      final TrackingCubit cubit = buildCubit();
      addTearDown(cubit.close);

      await selectedDepartCubit.select(departed);
      await Future<void>.delayed(Duration.zero);
      await selectedDepartCubit.select(cancelled);
      await Future<void>.delayed(Duration.zero);

      verify(() => repository.stop(reason: TrackingStopReason.cancelled))
          .called(1);
    });

    test('arrête le suivi quand le départ est désélectionné (clôture)',
        () async {
      final TrackingCubit cubit = buildCubit();
      addTearDown(cubit.close);

      await selectedDepartCubit.select(departed);
      await Future<void>.delayed(Duration.zero);
      selectedDepartCubit.clear();
      await Future<void>.delayed(Duration.zero);

      verify(() => repository.stop(reason: TrackingStopReason.manualClose))
          .called(1);
    });

    test('ne démarre rien pour un statut intermédiaire (boarding)', () async {
      buildCubit();

      await selectedDepartCubit.select(boarding);
      await Future<void>.delayed(Duration.zero);

      verifyNever(() => repository.start(
            departureUuid: any(named: 'departureUuid'),
            terminalSerial: any(named: 'terminalSerial'),
          ));
    });

    test('ne démarre pas si aucun terminal n\'est lié', () async {
      when(() => terminalLocalDataSource.readSerialNumber())
          .thenAnswer((_) async => null);
      buildCubit();

      await selectedDepartCubit.select(departed);
      await Future<void>.delayed(Duration.zero);

      verifyNever(() => repository.start(
            departureUuid: any(named: 'departureUuid'),
            terminalSerial: any(named: 'terminalSerial'),
          ));
    });
  });

  group('disponibilité (permission/GPS/précision)', () {
    test('reflète une condition bloquante quand le suivi ne peut pas démarrer',
        () async {
      when(() => repository.start(
            departureUuid: any(named: 'departureUuid'),
            terminalSerial: any(named: 'terminalSerial'),
          )).thenAnswer((_) async => TrackingAvailability.permissionDenied);

      final TrackingCubit cubit = buildCubit();
      addTearDown(cubit.close);

      await selectedDepartCubit.select(departed);
      await Future<void>.delayed(Duration.zero);

      expect(cubit.state.isActive, isFalse);
      expect(cubit.state.availability, TrackingAvailability.permissionDenied);
    });

    test('requestPermission() relaie la disponibilité mise à jour', () async {
      when(() => repository.requestAvailability())
          .thenAnswer((_) async => TrackingAvailability.gpsDisabled);

      final TrackingCubit cubit = buildCubit();
      addTearDown(cubit.close);

      final TrackingAvailability result = await cubit.requestPermission();

      expect(result, TrackingAvailability.gpsDisabled);
      expect(cubit.state.availability, TrackingAvailability.gpsDisabled);
    });
  });

  group('statut d\'exécution (file, réseau, synchro)', () {
    test('reflète file en attente et hors-ligne pendant un suivi actif',
        () async {
      final TrackingCubit cubit = buildCubit();
      addTearDown(cubit.close);

      await selectedDepartCubit.select(departed);
      await Future<void>.delayed(Duration.zero);

      statusController.add(const TrackingRuntimeStatus(
        isOnline: false,
        isSyncing: false,
        pendingCount: 4,
        lastPositionAt: null,
      ));
      await Future<void>.delayed(Duration.zero);

      expect(cubit.state.isActive, isTrue);
      expect(cubit.state.isOnline, isFalse);
      expect(cubit.state.pendingCount, 4);
    });

    test('bootstrap() relit la disponibilité et la file en attente', () async {
      when(() => repository.checkAvailability())
          .thenAnswer((_) async => TrackingAvailability.insufficientAccuracy);
      when(() => repository.totalPendingCount()).thenAnswer((_) async => 2);

      final TrackingCubit cubit = buildCubit();
      addTearDown(cubit.close);

      await cubit.bootstrap();

      verify(() => repository.bootstrap()).called(1);
      expect(
          cubit.state.availability, TrackingAvailability.insufficientAccuracy);
      expect(cubit.state.pendingCount, 2);
    });
  });
}
