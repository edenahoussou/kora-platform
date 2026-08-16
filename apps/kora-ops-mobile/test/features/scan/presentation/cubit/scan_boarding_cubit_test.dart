import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/manifeste/presentation/cubit/manifeste_cubit.dart';
import 'package:kora_ops_mobile/features/ops/data/mock_ops_data_source.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';
import 'package:kora_ops_mobile/features/ops/presentation/cubit/selected_depart_cubit.dart';
import 'package:kora_ops_mobile/features/printing/domain/usecases/scan_barcode.dart';
import 'package:kora_ops_mobile/features/scan/presentation/cubit/scan_boarding_cubit.dart';
import 'package:kora_ops_mobile/features/synchro/presentation/cubit/sync_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockScanBarcode extends Mock implements ScanBarcode {}

void main() {
  late MockScanBarcode scanBarcode;
  late SelectedDepartCubit selectedDepartCubit;
  late SyncCubit syncCubit;
  late ManifesteCubit manifesteCubit;

  setUp(() async {
    scanBarcode = MockScanBarcode();
    selectedDepartCubit = SelectedDepartCubit();
    syncCubit = SyncCubit();
    selectedDepartCubit.select(MockOpsDataSource.departs.first);
    manifesteCubit = ManifesteCubit(
        selectedDepartCubit: selectedDepartCubit, syncCubit: syncCubit);
    await Future<void>.delayed(const Duration(milliseconds: 800));
  });

  ScanBoardingCubit buildCubit() => ScanBoardingCubit(
      scanBarcode: scanBarcode, manifesteCubit: manifesteCubit);

  blocTest<ScanBoardingCubit, ScanBoardingState>(
    'marque le premier passager en attente comme embarqué et émet "valide"',
    build: () {
      when(() => scanBarcode())
          .thenAnswer((_) async => const Result<String>.success('QR-TEST'));
      return buildCubit();
    },
    act: (ScanBoardingCubit cubit) => cubit.scan(),
    expect: () => <dynamic>[
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.scanning),
      predicate<ScanBoardingState>(
        (s) =>
            s.status == ScanBoardingStatus.valide && s.matchedPassenger != null,
      ),
    ],
  );

  test('émet "refuse" quand plus aucun passager n\'est en attente', () async {
    when(() => scanBarcode())
        .thenAnswer((_) async => const Result<String>.success('QR-TEST'));

    // Marque tous les passagers en attente comme déjà traités.
    for (final Passenger passenger
        in manifesteCubit.state.passengers.toList()) {
      if (passenger.statut == BoardingStatus.enAttente) {
        manifesteCubit.updateStatut(passenger.id, BoardingStatus.refuse);
      }
    }

    final cubit = buildCubit();
    await cubit.scan();

    expect(cubit.state.status, ScanBoardingStatus.refuse);
  });

  blocTest<ScanBoardingCubit, ScanBoardingState>(
    'émet "erreur" quand le scanner échoue',
    build: () {
      when(() => scanBarcode()).thenAnswer((_) async =>
          const Result<String>.failure(HardwareFailure('Scanner HS')));
      return buildCubit();
    },
    act: (ScanBoardingCubit cubit) => cubit.scan(),
    expect: () => <dynamic>[
      predicate<ScanBoardingState>(
          (s) => s.status == ScanBoardingStatus.scanning),
      predicate<ScanBoardingState>(
        (s) =>
            s.status == ScanBoardingStatus.erreur && s.message == 'Scanner HS',
      ),
    ],
  );

  blocTest<ScanBoardingCubit, ScanBoardingState>(
    'reset() revient à l\'état initial',
    build: buildCubit,
    act: (ScanBoardingCubit cubit) => cubit.reset(),
    expect: () => <dynamic>[const ScanBoardingState()],
  );
}
