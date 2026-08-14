import '../entities/ticket_payload.dart';

/// Interface d'accès à l'imprimante thermique 58mm du terminal.
///
/// Deux implémentations : [MockThermalPrinter] (journalise le ticket
/// formaté sans imprimer, utilisée par défaut) et [P501ThermalPrinter] (SDK
/// natif via MethodChannel).
abstract interface class ThermalPrinter {
  /// Imprime le ticket correspondant à [payload] et retourne `true` en cas
  /// de succès matériel confirmé.
  ///
  /// Lève une [PrinterException] (`core/exceptions`) en cas d'erreur
  /// matérielle (papier absent, imprimante déconnectée, SDK en erreur…).
  Future<bool> printTicket(TicketPayload payload);
}
