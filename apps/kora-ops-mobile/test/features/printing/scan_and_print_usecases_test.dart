import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:kora_ops_mobile/core/exceptions/app_exceptions.dart';
import 'package:kora_ops_mobile/features/printing/domain/entities/ticket_payload.dart';
import 'package:kora_ops_mobile/features/printing/domain/services/barcode_scanner.dart';
import 'package:kora_ops_mobile/features/printing/domain/services/thermal_printer.dart';
import 'package:kora_ops_mobile/features/printing/domain/usecases/print_ticket.dart';
import 'package:kora_ops_mobile/features/printing/domain/usecases/scan_barcode.dart';

class MockBarcodeScannerService extends Mock implements BarcodeScanner {}

class MockThermalPrinterService extends Mock implements ThermalPrinter {}

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

  group('ScanBarcode', () {
    test('retourne un succès quand le scanner répond', () async {
      final scanner = MockBarcodeScannerService();
      when(() => scanner.scan()).thenAnswer((_) async => 'KORA-TICKET-000123');

      final result = await ScanBarcode(scanner)();

      expect(result.isSuccess, isTrue);
    });

    test('mappe ScannerException en HardwareFailure', () async {
      final scanner = MockBarcodeScannerService();
      when(() => scanner.scan()).thenThrow(const ScannerException('Échec scan'));

      final result = await ScanBarcode(scanner)();

      expect(result.isFailure, isTrue);
    });
  });

  group('PrintTicket', () {
    const TicketPayload payload = TicketPayload(
      tripRoute: 'Cotonou - Porto-Novo',
      tripDate: '14/08/2026',
      tripTime: '07:30',
      passengerName: 'Agent Test',
      seatNumber: '12A',
      qrData: 'KORA-TICKET-000123',
    );

    test('retourne un succès quand l\'imprimante confirme', () async {
      final printer = MockThermalPrinterService();
      when(() => printer.printTicket(any())).thenAnswer((_) async => true);

      final result = await PrintTicket(printer)(payload);

      expect(result.isSuccess, isTrue);
    });

    test('mappe PrinterException en HardwareFailure', () async {
      final printer = MockThermalPrinterService();
      when(() => printer.printTicket(any())).thenThrow(const PrinterException('Papier absent'));

      final result = await PrintTicket(printer)(payload);

      expect(result.isFailure, isTrue);
    });
  });
}
