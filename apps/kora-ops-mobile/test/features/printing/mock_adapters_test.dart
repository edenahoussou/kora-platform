import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/exceptions/app_exceptions.dart';
import 'package:kora_ops_mobile/features/printing/data/services/mock_barcode_scanner.dart';
import 'package:kora_ops_mobile/features/printing/data/services/mock_thermal_printer.dart';
import 'package:kora_ops_mobile/features/printing/domain/entities/border_manifest_payload.dart';
import 'package:kora_ops_mobile/features/printing/domain/entities/ticket_payload.dart';

void main() {
  group('MockBarcodeScanner', () {
    test('retourne une chaîne non vide par défaut', () async {
      final scanner = MockBarcodeScanner(simulatedDelay: Duration.zero);
      final String code = await scanner.scan();
      expect(code, isNotEmpty);
    });

    test('lève ScannerException quand shouldFail est true', () async {
      final scanner =
          MockBarcodeScanner(shouldFail: true, simulatedDelay: Duration.zero);
      expect(scanner.scan, throwsA(isA<ScannerException>()));
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
      final printer =
          MockThermalPrinter(shouldFail: true, simulatedDelay: Duration.zero);
      expect(
          () => printer.printTicket(payload), throwsA(isA<PrinterException>()));
    });
  });

  group('MockThermalPrinter — manifeste frontière (MEZ-94)', () {
    final BorderManifestPayload manifest = BorderManifestPayload(
      departureNumber: '9B1DEB4D',
      departureDate: '14/08/2026',
      departureTime: '07:30',
      lineCode: 'COT-LFW',
      route: 'Cotonou → Lomé',
      passengers: const <BorderManifestPassengerLine>[
        BorderManifestPassengerLine(
          nomComplet: 'Fatou Diop',
          nationalite: 'SN',
          documentNumero: 'A1234567',
          destination: 'Porto-Novo',
          siege: '12A',
        ),
      ],
      printedAt: DateTime(2026, 8, 14, 7, 5),
    );

    test('imprime un exemplaire et le conserve pour les assertions', () async {
      final printer = MockThermalPrinter(simulatedDelay: Duration.zero);

      final bool result = await printer.printBorderManifest(manifest,
          copyNumber: 2, totalCopies: 3);

      expect(result, isTrue);
      expect(printer.printedBorderManifests, hasLength(1));
      expect(printer.printedBorderManifests.single.join('\n'),
          contains('Exemplaire: 2/3'));
    });

    test('failOnCopies fait échouer uniquement les exemplaires ciblés',
        () async {
      final printer = MockThermalPrinter(
        simulatedDelay: Duration.zero,
        failOnCopies: <int>{2},
        failureKind: PrinterFailureKind.outOfPaper,
      );

      expect(await printer.printBorderManifest(manifest,
          copyNumber: 1, totalCopies: 3), isTrue);

      await expectLater(
        printer.printBorderManifest(manifest, copyNumber: 2, totalCopies: 3),
        throwsA(isA<PrinterException>()
            .having((PrinterException e) => e.kind, 'kind',
                PrinterFailureKind.outOfPaper)),
      );

      // Un exemplaire en échec n'est jamais compté comme imprimé.
      expect(printer.printedBorderManifests, hasLength(1));
    });

    test('shouldFail fait échouer tous les exemplaires', () async {
      final printer =
          MockThermalPrinter(shouldFail: true, simulatedDelay: Duration.zero);

      expect(
        () => printer.printBorderManifest(manifest,
            copyNumber: 1, totalCopies: 3),
        throwsA(isA<PrinterException>()),
      );
    });
  });

  group('PrinterFailureKind', () {
    test('chaque panne identifiée a un message dédié pour le convoyeur', () {
      expect(PrinterFailureKind.outOfPaper.userMessage(), contains('papier'));
      expect(PrinterFailureKind.notConnected.userMessage(),
          contains('Imprimante'));
      expect(PrinterFailureKind.overheated.userMessage(),
          contains('surchauffe'));
    });

    test('une panne inconnue réutilise le message du SDK', () {
      expect(PrinterFailureKind.unknown.userMessage('Code SDK 42'),
          'Code SDK 42');
      expect(PrinterFailureKind.unknown.userMessage(),
          contains('Erreur de l\'imprimante'));
    });

    test('PrinterException expose un message affichable', () {
      const PrinterException paper = PrinterException(
          'NO_PAPER', PrinterFailureKind.outOfPaper);
      expect(paper.userMessage, contains('papier'));
    });
  });
}
