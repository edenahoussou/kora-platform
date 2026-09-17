import 'package:dartz/dartz.dart';

import '../entities/ticket_document.dart';

/// Couche DOMAIN : le PDF officiel des billets.
abstract interface class TicketDocumentRepository {
  /// Le document déjà enregistré sur le téléphone, sans réseau.
  Future<TicketDocument?> saved(String ticketId);

  /// Télécharge le PDF du backend et l'enregistre, en remplaçant le
  /// précédent.
  ///
  /// Hors ligne, un document déjà enregistré est rendu marqué
  /// [TicketDocument.isStale] plutôt qu'un échec : le voyageur a son billet.
  Future<Either<TicketDocumentFailure, TicketDocument>> download(
    String ticketId,
  );
}
