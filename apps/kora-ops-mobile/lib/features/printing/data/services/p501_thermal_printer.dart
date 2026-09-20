import 'package:flutter/services.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../domain/entities/border_manifest_payload.dart';
import '../../domain/entities/ticket_payload.dart';
import '../../domain/services/thermal_printer.dart';
import '../formatters/border_manifest_formatter.dart';
import '../formatters/ticket_formatter.dart';

/// Implémentation matérielle pilotant l'imprimante thermique 58mm intégrée
/// du terminal P501 via `MethodChannel`.
///
/// **À faire côté natif** (voir document.md § Adaptateurs P501) : enregistrer
/// un `MethodChannel` nommé [channelName] dans
/// `android/.../MainActivity.kt` et gérer :
///
/// - `printTicket` — arguments `{ "lines": List<String>, "qrData": String }` :
///   le texte est déjà formaté à 32 caractères par [TicketFormatter], le SDK
///   natif n'a qu'à l'imprimer tel quel et générer le QR à partir de
///   `qrData`.
/// - `printBorderManifest` (MEZ-94) — arguments
// ignore: unintended_html_in_doc_comment
///   `{ "lines": List<String>, "logoPng": Uint8List?, "copyNumber": int,
///   "totalCopies": int }` : imprimer d'abord le logo (`logoPng`, PNG
///   quelconque à convertir en noir et blanc, ignoré s'il est `null`), puis
///   `lines` **tel quel** (déjà formatées à 32 caractères par
///   [BorderManifestFormatter], aucune coupe ni réécriture), puis avancer
///   le papier pour la découpe. Un seul exemplaire par appel. Retourner
///   `true` **uniquement** une fois le SDK ayant confirmé la fin
///   d'impression ; sinon lever une `PlatformException` dont le `code` est
///   l'un de : `NO_PRINTER` / `PRINTER_DISCONNECTED` (imprimante absente),
///   `OUT_OF_PAPER` / `NO_PAPER` (papier), `OVERHEAT` (surchauffe) — tout
///   autre code est traité comme une erreur générique.
class P501ThermalPrinter implements ThermalPrinter {
  P501ThermalPrinter({
    MethodChannel? channel,
    Future<Uint8List?> Function()? logoLoader,
  })  : _channel = channel ?? const MethodChannel(channelName),
        _logoLoader = logoLoader ?? _loadDefaultLogo;

  static const String channelName = 'kora.ops/p501_printer';

  /// Logo KORA imprimé en tête du manifeste frontière.
  static const String logoAsset = 'assets/images/kora_logo.png';

  final MethodChannel _channel;
  final Future<Uint8List?> Function() _logoLoader;

  @override
  Future<bool> printTicket(TicketPayload payload) async {
    final List<String> lines = TicketFormatter.format(payload);

    try {
      final bool? success =
          await _channel.invokeMethod<bool>('printTicket', <String, Object>{
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

  @override
  Future<bool> printBorderManifest(
    BorderManifestPayload payload, {
    required int copyNumber,
    required int totalCopies,
  }) async {
    final List<String> lines = BorderManifestFormatter.format(
      payload,
      copyNumber: copyNumber,
      totalCopies: totalCopies,
    );
    // Un logo introuvable ne doit jamais empêcher l'impression du manifeste :
    // l'en-tête texte « KORA EXPRESS » reste imprimé dans `lines`.
    final Uint8List? logo = await _logoLoader();

    try {
      final bool? success = await _channel
          .invokeMethod<bool>('printBorderManifest', <String, Object?>{
        'lines': lines,
        'logoPng': logo,
        'copyNumber': copyNumber,
        'totalCopies': totalCopies,
      });
      return success ?? false;
    } on PlatformException catch (error) {
      throw PrinterException(
        error.message ?? 'Erreur de l\'imprimante P501.',
        _kindOf(error.code),
      );
    } on MissingPluginException {
      throw const PrinterException(
        'Canal natif P501 non implémenté sur cet appareil (MissingPluginException).',
        PrinterFailureKind.notConnected,
      );
    }
  }

  static PrinterFailureKind _kindOf(String code) => switch (code) {
        'NO_PRINTER' ||
        'PRINTER_DISCONNECTED' =>
          PrinterFailureKind.notConnected,
        'OUT_OF_PAPER' || 'NO_PAPER' => PrinterFailureKind.outOfPaper,
        'OVERHEAT' => PrinterFailureKind.overheated,
        _ => PrinterFailureKind.unknown,
      };

  static Future<Uint8List?> _loadDefaultLogo() async {
    try {
      final ByteData data = await rootBundle.load(logoAsset);
      return data.buffer.asUint8List(data.offsetInBytes, data.lengthInBytes);
    } on Object {
      return null;
    }
  }
}
