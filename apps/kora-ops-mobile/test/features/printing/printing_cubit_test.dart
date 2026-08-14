import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/printing/domain/entities/ticket_payload.dart';
import 'package:kora_ops_mobile/features/printing/domain/usecases/print_ticket.dart';
import 'package:kora_ops_mobile/features/printing/domain/usecases/scan_barcode.dart';
import 'package:kora_ops_mobile/features/printing/presentation/cubit/printing_cubit.dart';

class MockScanBarcode extends Mock implements ScanBarcode {}

class MockPrintTicket extends Mock implements PrintTicket {}

void main() {
  setUpAll(() {
    registerFallbackValue(
      const TicketPayload(
        tripRoute: '',
        tripDate: '',
        tripTime: '',
        passengerName: '',
        seatNumber: '',
        qrData: '',
      ),
    );
  });

  late MockScanBarcode scanBarcode;
  late MockPrintTicket printTicket;

  setUp(() {
    scanBarcode = MockScanBarcode();
    printTicket = MockPrintTicket();
  });

  PrintingCubit buildCubit() =>
      PrintingCubit(scanBarcode: scanBarcode, printTicket: printTicket);

  blocTest<PrintingCubit, PrintingState>(
    'émet [Scanning, Idle(code)] quand le scan réussit',
    build: () {
      when(() => scanBarcode()).thenAnswer(
        (_) async => const Result<String>.success('KORA-TICKET-000123'),
      );
      return buildCubit();
    },
    act: (PrintingCubit cubit) => cubit.scan(),
    expect: () => <PrintingState>[
      const PrintingScanning(),
      const PrintingIdle(lastScannedCode: 'KORA-TICKET-000123'),
    ],
  );

  blocTest<PrintingCubit, PrintingState>(
    'émet [Scanning, Failure] quand le scan échoue',
    build: () {
      when(() => scanBarcode())
          .thenAnswer((_) async => const Result<String>.failure(HardwareFailure('Scan HS')));
      return buildCubit();
    },
    act: (PrintingCubit cubit) => cubit.scan(),
    expect: () => <PrintingState>[const PrintingScanning(), const PrintingFailure('Scan HS')],
  );

  blocTest<PrintingCubit, PrintingState>(
    'émet [InProgress, Success] quand l\'impression réussit',
    build: () {
      when(() => printTicket(any())).thenAnswer((_) async => const Result<bool>.success(true));
      return buildCubit();
    },
    act: (PrintingCubit cubit) => cubit.printTestTicket(),
    verify: (PrintingCubit cubit) => expect(cubit.state, isA<PrintingSuccess>()),
  );

  blocTest<PrintingCubit, PrintingState>(
    'émet Failure quand l\'impression échoue',
    build: () {
      when(() => printTicket(any()))
          .thenAnswer((_) async => const Result<bool>.failure(HardwareFailure('Papier absent')));
      return buildCubit();
    },
    act: (PrintingCubit cubit) => cubit.printTestTicket(),
    verify: (PrintingCubit cubit) => expect(cubit.state, const PrintingFailure('Papier absent')),
  );
}
