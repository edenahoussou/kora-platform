import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/exceptions/app_exceptions.dart';
import 'package:kora_ops_mobile/features/printing/data/services/mock_barcode_scanner.dart';
import 'package:kora_ops_mobile/features/printing/data/services/mock_thermal_printer.dart';
import 'package:kora_ops_mobile/features/printing/domain/entities/ticket_payload.dart';

void main() {
  group('MockBarcodeScanner', () {
    test('retourne une chaîne non vide par défaut', () async {
      final scanner = MockBarcodeScanner(simulatedDelay: Duration.zero);
      final String code = await scanner.scan();
      expect(code, isNotEmpty);
    });

    test('lève ScannerException quand shouldFail est true', () async {
      final scanner = MockBarcodeScanner(shouldFail: true, simulatedDelay: Duration.zero);
      expect(() => scanner.scan(), throwsA(isA<ScannerException>()));
    });
  });

  group('MockThermalPrinter', () {
    const TicketPayload payload = TicketPayload(
      tripRoute: 'Cotonou - Porto-Novo',
      tripDate: '14/08/2026',
      tripTime: '07:30',
      passengerName: 'Agent Test',
      seatNumber: '12A',
      qrData: 'KORA-TICKET-000123',
    );

    test('retourne true pour un ticket valide', () async {
      final printer = MockThermalPrinter(simulatedDelay: Duration.zero);
      final bool result = await printer.printTicket(payload);
      expect(result, isTrue);
    });

    test('lève PrinterException quand shouldFail est true', () async {
      final printer = MockThermalPrinter(shouldFail: true, simulatedDelay: Duration.zero);
      expect(() => printer.printTicket(payload), throwsA(isA<PrinterException>()));
    });
  });
}
