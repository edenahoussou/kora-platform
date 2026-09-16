import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:kora_ops_mobile/features/boarding/domain/entities/boarding_outcome.dart';
import 'package:kora_ops_mobile/features/boarding/domain/entities/boarding_outcome_status.dart';
import 'package:kora_ops_mobile/features/boarding/domain/repositories/boarding_repository.dart';
import 'package:kora_ops_mobile/features/manifeste/domain/entities/manifest_snapshot.dart';
import 'package:kora_ops_mobile/features/manifeste/domain/repositories/manifeste_repository.dart';
import 'package:kora_ops_mobile/features/manifeste/presentation/cubit/manifeste_cubit.dart';
import 'package:kora_ops_mobile/features/ops/data/datasources/selected_departure_local_datasource.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/depart.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';
import 'package:kora_ops_mobile/features/ops/presentation/cubit/selected_depart_cubit.dart';
import 'package:kora_ops_mobile/features/printing/domain/usecases/scan_barcode.dart';
import 'package:kora_ops_mobile/features/scan/presentation/cubit/scan_boarding_cubit.dart';
import 'package:kora_ops_mobile/features/synchro/domain/repositories/sync_repository.dart';
import 'package:kora_ops_mobile/features/synchro/presentation/cubit/sync_cubit.dart';
import 'package:kora_ops_mobile/features/terminal/domain/entities/terminal_activity_type.dart';
import 'package:kora_ops_mobile/features/terminal/domain/repositories/terminal_repository.dart';
import 'package:mocktail/mocktail.dart';
import 'package:uuid/uuid.dart';

class MockScanBarcode extends Mock implements ScanBarcode {}

class MockBoardingRepository extends Mock implements BoardingRepository {}

class MockManifesteRepository extends Mock implements ManifesteRepository {}

class MockTerminalRepository extends Mock implements TerminalRepository {}

class MockSyncRepository extends Mock implements SyncRepository {}

class MockSelectedDepartureLocalDataSource extends Mock
    implements SelectedDepartureLocalDataSource {}

class MockAuthCubit extends Mock implements AuthCubit {}

void main() {
  setUpAll(() {
    registerFallbackValue(TerminalActivityType.scan);
    registerFallbackValue(const Depart(
      id: 'fallback',
      route: '—',
      date: '—',
      heure: '—',
      vehiculeImmat: '—',
      totalPlaces: 0,
      placesOccupees: 0,
    ));
  });

  late MockScanBarcode scanBarcode;
  late MockBoardingRepository boardingRepository;
  late MockManifesteRepository manifesteRepository;
  late MockTerminalRepository terminalRepository;
  late SelectedDepartCubit selectedDepartCubit;
  late SyncCubit syncCubit;
  late ManifesteCubit manifesteCubit;

  const Depart depart = Depart(
    id: 'dep-uuid-1',
    route: 'Cotonou → Porto-Novo',
    date: '16/08/2026',
    heure: '07:30',
    vehiculeImmat: 'AB-1234-RB',
    totalPlaces: 45,
    placesOccupees: 38,
  );

  const Passenger passenger = Passenger(
    id: 'ticket-1',
    nomComplet: 'Aïcha Hounkpé',
    siege: '12A',
    statut: BoardingStatus.enAttente,
    segmentOrigine: 'Cotonou',
    segmentDestination: 'Porto-Novo',
    ticketId: 'AB12CD',
  );

  ScanBoardingCubit buildCubit() => ScanBoardingCubit(
        scanBarcode: scanBarcode,
        boardingRepository: boardingRepository,
        manifesteCubit: manifesteCubit,
        selectedDepartCubit: selectedDepartCubit,
        terminalRepository: terminalRepository,
        uuid: const Uuid(),
      );

  setUp(() async {
    scanBarcode = MockScanBarcode();
    boardingRepository = MockBoardingRepository();
    manifesteRepository = MockManifesteRepository();
    terminalRepository = MockTerminalRepository();
    final MockSelectedDepartureLocalDataSource selectedDepartLocal =
        MockSelectedDepartureLocalDataSource();
    when(() => selectedDepartLocal.saveSnapshot(any()))
        .thenAnswer((_) async {});
    when(selectedDepartLocal.clear).thenAnswer((_) async {});
    final MockAuthCubit authCubit = MockAuthCubit();
    when(() => authCubit.stream).thenAnswer((_) => const Stream.empty());
    selectedDepartCubit = SelectedDepartCubit(selectedDepartLocal, authCubit);
    // Mocks jamais stubbés : ManifesteCubit n'exerce que `enqueue()` (via
    // `updateStatut`), qui ne les appelle pas — voir SyncCubit.enqueue.
    // `markBoarded` (utilisé par ScanBoardingCubit) ne passe pas non plus
    // par SyncCubit.
    syncCubit = SyncCubit(MockSyncRepository(), MockBoardingRepository());

    when(() => manifesteRepository.load(depart.id)).thenAnswer(
      (_) async => Result<ManifestSnapshot>.success(
        ManifestSnapshot(
          passengers: const <Passenger>[passenger],
          version: 'v1',
          fetchedAt: DateTime(2026, 8, 16, 7),
          isStale: false,
          isFromCache: false,
        ),
      ),
    );
    when(() => terminalRepository.logActivity(
          any(),
          context: any(named: 'context'),
        )).thenAnswer((_) async {});

    await selectedDepartCubit.select(depart);
    manifesteCubit = ManifesteCubit(
      selectedDepartCubit: selectedDepartCubit,
      syncCubit: syncCubit,
      repository: manifesteRepository,
    );
    await manifesteCubit.stream
        .firstWhere((ManifesteState s) => s.status == ManifesteStatus.success);
  });

  blocTest<ScanBoardingCubit, ScanBoardingState>(
    'scan nominal : billet valide, manifeste local mis à jour immédiatement',
    setUp: () {
      when(() => scanBarcode())
          .thenAnswer((_) async => const Result<String>.success('QR-1'));
      when(() => boardingRepository.scan(
            departureUuid: depart.id,
            qrPayload: 'QR-1',
            shortCode: null,
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: any(named: 'localRecordedAt'),
          )).thenAnswer((_) async => const Result<BoardingOutcome>.success(
            BoardingOutcome(
              status: BoardingOutcomeStatus.valid,
              message: 'Billet valide.',
              ticketUuid: 'ticket-1',
            ),
          ));
    },
    build: buildCubit,
    act: (cubit) => cubit.scan(),
    expect: () => <dynamic>[
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.scanning),
      predicate<ScanBoardingState>((s) =>
          s.status == ScanBoardingStatus.valide &&
          s.matchedPassenger?.id == 'ticket-1'),
    ],
    verify: (_) {
      expect(manifesteCubit.byId('ticket-1')?.statut, BoardingStatus.embarque);
    },
  );

  blocTest<ScanBoardingCubit, ScanBoardingState>(
    'un scan déjà en cours ignore un second appel concurrent',
    setUp: () {
      when(() => scanBarcode()).thenAnswer(
        (_) => Future<Result<String>>.delayed(
          const Duration(milliseconds: 50),
          () => const Result<String>.success('QR-1'),
        ),
      );
      when(() => boardingRepository.scan(
            departureUuid: any(named: 'departureUuid'),
            qrPayload: any(named: 'qrPayload'),
            shortCode: any(named: 'shortCode'),
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: any(named: 'localRecordedAt'),
          )).thenAnswer((_) async => const Result<BoardingOutcome>.success(
            BoardingOutcome(
              status: BoardingOutcomeStatus.valid,
              message: 'Billet valide.',
              ticketUuid: 'ticket-1',
            ),
          ));
    },
    build: buildCubit,
    act: (cubit) async {
      final first = cubit.scan();
      final second = cubit.scan(); // ignoré : déjà "scanning"
      await Future.wait(<Future<void>>[first, second]);
    },
    verify: (_) {
      verify(() => scanBarcode()).called(1);
    },
  );

  blocTest<ScanBoardingCubit, ScanBoardingState>(
    'mauvais départ',
    setUp: () {
      when(() => scanBarcode())
          .thenAnswer((_) async => const Result<String>.success('QR-2'));
      when(() => boardingRepository.scan(
            departureUuid: depart.id,
            qrPayload: 'QR-2',
            shortCode: null,
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: any(named: 'localRecordedAt'),
          )).thenAnswer((_) async => const Result<BoardingOutcome>.success(
            BoardingOutcome(
              status: BoardingOutcomeStatus.wrongDeparture,
              message: 'Ce billet ne correspond pas à ce départ.',
              ticketUuid: 'ticket-2',
            ),
          ));
    },
    build: buildCubit,
    act: (cubit) => cubit.scan(),
    expect: () => <dynamic>[
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.scanning),
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.mauvaisDepart),
    ],
  );

  blocTest<ScanBoardingCubit, ScanBoardingState>(
    'billet inconnu (QR illisible/non signé)',
    setUp: () {
      when(() => scanBarcode())
          .thenAnswer((_) async => const Result<String>.success('GARBAGE'));
      when(() => boardingRepository.scan(
            departureUuid: depart.id,
            qrPayload: 'GARBAGE',
            shortCode: null,
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: any(named: 'localRecordedAt'),
          )).thenAnswer((_) async => const Result<BoardingOutcome>.success(
            BoardingOutcome(
              status: BoardingOutcomeStatus.ticketUnknown,
              message: 'Billet inconnu ou signature invalide.',
            ),
          ));
    },
    build: buildCubit,
    act: (cubit) => cubit.scan(),
    expect: () => <dynamic>[
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.scanning),
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.billetInconnu),
    ],
  );

  blocTest<ScanBoardingCubit, ScanBoardingState>(
    'hors ligne : en attente de synchronisation, affiché sans erreur',
    setUp: () {
      when(() => scanBarcode())
          .thenAnswer((_) async => const Result<String>.success('QR-3'));
      when(() => boardingRepository.scan(
            departureUuid: depart.id,
            qrPayload: 'QR-3',
            shortCode: null,
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: any(named: 'localRecordedAt'),
          )).thenAnswer((_) async => const Result<BoardingOutcome>.success(
            BoardingOutcome(
              status: BoardingOutcomeStatus.pendingSync,
              message: 'Enregistré hors ligne — en attente de synchronisation.',
            ),
          ));
    },
    build: buildCubit,
    act: (cubit) => cubit.scan(),
    expect: () => <dynamic>[
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.scanning),
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.enAttenteSync),
    ],
  );

  blocTest<ScanBoardingCubit, ScanBoardingState>(
    'recherche manuelle par code court puis soumission',
    setUp: () {
      when(() => boardingRepository.scan(
            departureUuid: depart.id,
            qrPayload: null,
            shortCode: 'AB12CD',
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: any(named: 'localRecordedAt'),
          )).thenAnswer((_) async => const Result<BoardingOutcome>.success(
            BoardingOutcome(
              status: BoardingOutcomeStatus.valid,
              message: 'Billet valide.',
              ticketUuid: 'ticket-1',
            ),
          ));
    },
    build: buildCubit,
    act: (cubit) async {
      cubit.searchManual('ab12');
      await cubit.submitManual(passenger);
    },
    expect: () => <dynamic>[
      predicate<ScanBoardingState>(
          (s) => s.manualResults.single.id == 'ticket-1'),
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.scanning),
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.valide),
    ],
  );

  blocTest<ScanBoardingCubit, ScanBoardingState>(
    'aucun départ sélectionné : erreur immédiate, jamais d\'appel réseau',
    setUp: () {
      selectedDepartCubit.clear();
      when(() => scanBarcode())
          .thenAnswer((_) async => const Result<String>.success('QR-1'));
    },
    build: buildCubit,
    act: (cubit) => cubit.scan(),
    expect: () => <dynamic>[
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.scanning),
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.erreur),
    ],
    verify: (_) {
      verifyNever(() => boardingRepository.scan(
            departureUuid: any(named: 'departureUuid'),
            qrPayload: any(named: 'qrPayload'),
            shortCode: any(named: 'shortCode'),
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: any(named: 'localRecordedAt'),
          ));
    },
  );
}
