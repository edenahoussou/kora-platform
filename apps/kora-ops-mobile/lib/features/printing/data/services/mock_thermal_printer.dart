import '../../../../core/exceptions/app_exceptions.dart';
import '../../../../core/utils/app_logger.dart';
import '../../domain/entities/ticket_payload.dart';
import '../../domain/services/thermal_printer.dart';
import '../formatters/ticket_formatter.dart';

/// Implémentation utilisée en développement : ne pilote aucun matériel,
/// journalise le ticket formaté (utile pour vérifier visuellement le rendu
/// 58mm sans terminal P501) et retourne `true`.
///
/// Passer `shouldFail: true` pour simuler une panne matérielle.
class MockThermalPrinter implements ThermalPrinter {
  MockThermalPrinter(
      {this.shouldFail = false,
      this.simulatedDelay = const Duration(milliseconds: 600)});

  final bool shouldFail;
  final Duration simulatedDelay;

  @override
  Future<bool> printTicket(TicketPayload payload) async {
    final List<String> lines = TicketFormatter.format(payload);
    AppLogger.i('MOCK PRINT (58mm) —\n${lines.join('\n')}');

    await Future<void>.delayed(simulatedDelay);

    if (shouldFail) {
      throw const PrinterException('Impression simulée en échec (mock).');
    }
    return TicketFormatter.isValid(lines);
  }
}
