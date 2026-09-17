// Un dépôt de PDF officiels qui ne parle à personne.

import 'dart:async';

import 'package:dartz/dartz.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/ticket_document.dart';
import 'package:kora_app_passenger/features/booking/domain/repositories/ticket_document_repository.dart';

class StubTicketDocumentRepository implements TicketDocumentRepository {
  StubTicketDocumentRepository({
    this.savedDocument,
    List<Either<TicketDocumentFailure, TicketDocument>>? results,
  }) : results = results ?? [];

  /// Ce que rend [saved] : le document déjà sur le téléphone.
  TicketDocument? savedDocument;

  /// Les réponses de [download], dans l'ordre. Vide : succès.
  final List<Either<TicketDocumentFailure, TicketDocument>> results;

  /// Posé, il retient chaque téléchargement jusqu'à ce qu'on le complète —
  /// de quoi observer l'état « en cours ».
  Completer<void>? gate;

  int downloads = 0;

  static TicketDocument documentFor(String ticketId) => TicketDocument(
    ticketId: ticketId,
    fileName: 'KORA-$ticketId.pdf',
    path: '/tmp/KORA-$ticketId.pdf',
  );

  @override
  Future<TicketDocument?> saved(String ticketId) async => savedDocument;

  @override
  Future<Either<TicketDocumentFailure, TicketDocument>> download(
    String ticketId,
  ) async {
    downloads++;

    final gate = this.gate;
    if (gate != null) await gate.future;

    return results.isEmpty ? Right(documentFor(ticketId)) : results.removeAt(0);
  }
}
