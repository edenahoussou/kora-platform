// L'état des PDF officiels, partagé par la liste, le détail et les billets
// du groupe.

import 'dart:async';

import 'package:dartz/dartz.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/ticket_document.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/ticket_document_cubit.dart';

import '../../../../support/stub_ticket_document_repository.dart';

void main() {
  const ticketId = 'ticket-1';

  test('un second appui pendant le téléchargement ne relance rien', () async {
    final repository = StubTicketDocumentRepository()..gate = Completer();
    final cubit = TicketDocumentCubit(repository);
    addTearDown(cubit.close);

    final first = cubit.download(ticketId);
    expect(cubit.state.of(ticketId).isDownloading, isTrue);

    expect(await cubit.download(ticketId), isNull);
    repository.gate!.complete();
    await first;

    expect(repository.downloads, 1);
    expect(cubit.state.of(ticketId).phase, TicketDocumentPhase.ready);
  });

  test('après un échec, réessayer mène au billet', () async {
    final repository = StubTicketDocumentRepository(
      results: [const Left(TicketDocumentFailure(TicketDocumentError.network))],
    );
    final cubit = TicketDocumentCubit(repository);
    addTearDown(cubit.close);

    await cubit.download(ticketId);
    expect(cubit.state.of(ticketId).error, TicketDocumentError.network);

    await cubit.download(ticketId);
    final entry = cubit.state.of(ticketId);
    expect(entry.phase, TicketDocumentPhase.ready);
    expect(entry.document, StubTicketDocumentRepository.documentFor(ticketId));
  });

  test('le billet déjà enregistré s’ouvre sans réseau', () async {
    final saved = StubTicketDocumentRepository.documentFor(ticketId);
    final repository = StubTicketDocumentRepository(savedDocument: saved);
    final cubit = TicketDocumentCubit(repository);
    addTearDown(cubit.close);

    await cubit.restore(ticketId);

    expect(cubit.state.of(ticketId).document, saved);
    expect(repository.downloads, 0);
  });

  test('un refus d’accès retire la copie locale de l’écran', () async {
    final repository = StubTicketDocumentRepository(
      results: [
        Right(StubTicketDocumentRepository.documentFor(ticketId)),
        const Left(TicketDocumentFailure(TicketDocumentError.inaccessible)),
      ],
    );
    final cubit = TicketDocumentCubit(repository);
    addTearDown(cubit.close);

    await cubit.download(ticketId);
    await cubit.download(ticketId);

    final entry = cubit.state.of(ticketId);
    expect(entry.error, TicketDocumentError.inaccessible);
    expect(entry.document, isNull);
  });

  test('une erreur réseau garde le billet déjà obtenu', () async {
    final repository = StubTicketDocumentRepository(
      results: [
        Right(StubTicketDocumentRepository.documentFor(ticketId)),
        const Left(TicketDocumentFailure(TicketDocumentError.unavailable)),
      ],
    );
    final cubit = TicketDocumentCubit(repository);
    addTearDown(cubit.close);

    await cubit.download(ticketId);
    await cubit.download(ticketId);

    expect(cubit.state.of(ticketId).document, isNotNull);
  });
}
