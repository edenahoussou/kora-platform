import '../../../../core/errors/failures.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../../../core/result/result.dart';
import '../entities/ticket_payload.dart';
import '../services/thermal_printer.dart';

class PrintTicket {
  const PrintTicket(this._printer);

  final ThermalPrinter _printer;

  Future<Result<bool>> call(TicketPayload payload) async {
    try {
      final bool success = await _printer.printTicket(payload);
      return Result<bool>.success(success);
    } on PrinterException catch (error) {
      return Result<bool>.failure(HardwareFailure(error.message));
    }
  }
}
