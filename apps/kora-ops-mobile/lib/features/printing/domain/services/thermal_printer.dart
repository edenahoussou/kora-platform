import '../entities/border_manifest_payload.dart';
import '../entities/ticket_payload.dart';

/// Interface d'accès à l'imprimante thermique 58mm du terminal.
///
/// Deux implémentations : [MockThermalPrinter] (journalise le document
/// formaté sans imprimer, utilisée par défaut) et [P501ThermalPrinter] (SDK
/// natif via MethodChannel).
abstract interface class ThermalPrinter {
  /// Imprime le ticket correspondant à [payload] et retourne `true` en cas
  /// de succès matériel confirmé.
  ///
  /// Lève une [PrinterException] (`core/exceptions`) en cas d'erreur
  /// matérielle (papier absent, imprimante déconnectée, SDK en erreur…).
  Future<bool> printTicket(TicketPayload payload);

  /// Imprime **un seul exemplaire** ([copyNumber] sur [totalCopies]) du
  /// manifeste frontière (MEZ-94) et retourne `true` uniquement si
  /// l'imprimante confirme la sortie du document.
  ///
  /// Un exemplaire par appel — jamais une boucle interne — pour que
  /// l'appelant puisse persister le résultat de chaque exemplaire et
  /// reprendre sans jamais en imprimer un de trop.
  ///
  /// Lève une [PrinterException] (avec son `kind` : papier absent,
  /// imprimante déconnectée…) en cas d'erreur matérielle.
  Future<bool> printBorderManifest(
    BorderManifestPayload payload, {
    required int copyNumber,
    required int totalCopies,
  });
}
