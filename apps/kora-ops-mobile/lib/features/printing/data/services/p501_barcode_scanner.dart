import 'package:flutter/services.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../domain/services/barcode_scanner.dart';

/// Implémentation matérielle pilotant le scanner intégré du terminal P501
/// via `MethodChannel`.
///
/// **À faire côté natif** (non fourni par ce scaffold — aucun SDK P501 n'a
/// été fourni au moment de la génération, voir document.md § Adaptateurs
/// P501) : enregistrer un `MethodChannel` nommé [channelName] dans
/// `android/.../MainActivity.kt`, gérer la méthode `scan` et retourner la
/// chaîne décodée (ou lever une erreur native mappée en `PlatformException`).
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
