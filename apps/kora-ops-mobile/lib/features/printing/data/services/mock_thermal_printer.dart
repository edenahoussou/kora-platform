import '../../../../core/exceptions/app_exceptions.dart';
import '../../../../core/utils/app_logger.dart';
import '../../domain/entities/border_manifest_payload.dart';
import '../../domain/entities/ticket_payload.dart';
import '../../domain/services/thermal_printer.dart';
import '../formatters/border_manifest_formatter.dart';
import '../formatters/ticket_formatter.dart';

/// Implémentation utilisée en développement : ne pilote aucun matériel,
/// journalise le document formaté (utile pour vérifier visuellement le
/// rendu 58mm sans terminal P501) et retourne `true`.
///
/// Passer `shouldFail: true` pour simuler une panne matérielle. Pour le
/// manifeste frontière, [failOnCopies] fait échouer des exemplaires précis
/// (ex : `{2}` = l'exemplaire 2 échoue, les autres sortent) avec la nature
/// de panne [failureKind] (papier absent, imprimante absente…).
class MockThermalPrinter implements ThermalPrinter {
  MockThermalPrinter({
    this.shouldFail = false,
    this.simulatedDelay = const Duration(milliseconds: 600),
    Set<int>? failOnCopies,
    this.failureKind = PrinterFailureKind.unknown,
  }) : failOnCopies = failOnCopies ?? <int>{};

  final bool shouldFail;
  final Duration simulatedDelay;

  /// Numéros d'exemplaires du manifeste frontière qui échouent. Modifiable
  /// entre deux appels (`clear()`) pour simuler « l'incident est réglé ».
  final Set<int> failOnCopies;

  /// Nature de la panne simulée pour le manifeste frontière.
  PrinterFailureKind failureKind;

  /// Exemplaires du manifeste frontière « imprimés » avec succès, dans
  /// l'ordre — pour les assertions de test.
  final List<List<String>> printedBorderManifests = <List<String>>[];

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
    // Le contenu (noms, n° de document) n'est journalisé qu'en debug : le
    // mock ne tourne jamais en production (voir `AppConfig.useMockHardware`).
    AppLogger.i(
      'MOCK PRINT manifeste frontière (58mm) exemplaire '
      '$copyNumber/$totalCopies —\n${lines.join('\n')}',
    );

    await Future<void>.delayed(simulatedDelay);

    if (shouldFail || failOnCopies.contains(copyNumber)) {
      throw PrinterException(
        shouldFail && failureKind == PrinterFailureKind.unknown
            ? 'Impression simulée en échec (mock).'
            : failureKind.userMessage(),
        failureKind,
      );
    }

    final bool valid = BorderManifestFormatter.isValid(lines);
    if (valid) printedBorderManifests.add(lines);
    return valid;
  }
}
