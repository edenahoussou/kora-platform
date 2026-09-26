import 'package:flutter/services.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../domain/services/barcode_scanner.dart';

/// Implémentation matérielle pilotant le scanner intégré du terminal P501
/// via `MethodChannel`.
///
/// Côté natif (`android/.../MainActivity.kt`) : un `BroadcastReceiver`
/// écoute la diffusion "scan.rcv.message" émise par l'app pré-installée
/// "Scan Tools" du terminal à l'appui de la gâchette physique — voir la
/// docstring de `MainActivity` pour le détail du contrat (extras
/// "barocode"/"length") et le réglage requis côté terminal (Scan Tools →
/// Settings → Output Mode → "Broadcast Mode").
class P501BarcodeScanner implements BarcodeScanner {
  P501BarcodeScanner({MethodChannel? channel})
      : _channel = channel ?? const MethodChannel(channelName);

  static const String channelName = 'kora.ops/p501_scanner';

  final MethodChannel _channel;

  @override
  Future<String> scan() async {
    try {
      final String? result = await _channel.invokeMethod<String>('scan');
      if (result == null || result.trim().isEmpty) {
        throw const ScannerException('Scan annulé ou vide.');
      }
      return result;
    } on PlatformException catch (error) {
      throw ScannerException(error.message ?? 'Erreur du scanner P501.');
    } on MissingPluginException {
      throw const ScannerException(
        'Canal natif P501 non implémenté sur cet appareil (MissingPluginException).',
      );
    }
  }
}
