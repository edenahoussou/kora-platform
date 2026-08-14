import 'package:flutter/services.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../domain/entities/ticket_payload.dart';
import '../../domain/services/thermal_printer.dart';
import '../formatters/ticket_formatter.dart';

/// Implémentation matérielle pilotant l'imprimante thermique 58mm intégrée
/// du terminal P501 via `MethodChannel`.
///
/// **À faire côté natif** (voir document.md § Adaptateurs P501) : enregistrer
/// un `MethodChannel` nommé [channelName] dans
/// `android/.../MainActivity.kt`, gérer la méthode `printTicket` avec les
/// arguments `{ "lines": List<String>, "qrData": String }` — le texte est
/// déjà formaté à 32 caractères par [TicketFormatter], le SDK natif n'a
/// qu'à l'imprimer tel quel et générer le QR à partir de `qrData`.
class P501ThermalPrinter implements ThermalPrinter {
  P501ThermalPrinter({MethodChannel? channel})
      : _channel = channel ?? const MethodChannel(channelName);

  static const String channelName = 'kora.ops/p501_printer';

  final MethodChannel _channel;

  @override
  Future<bool> printTicket(TicketPayload payload) async {
    final List<String> lines = TicketFormatter.format(payload);

    try {
      final bool? success = await _channel.invokeMethod<bool>('printTicket', <String, Object>{
        'lines': lines,
        'qrData': payload.qrData,
      });
      return success ?? false;
    } on PlatformException catch (error) {
      throw PrinterException(error.message ?? 'Erreur de l\'imprimante P501.');
    } on MissingPluginException {
      throw const PrinterException(
        'Canal natif P501 non implémenté sur cet appareil (MissingPluginException).',
      );
    }
  }
}
