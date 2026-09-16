import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/ticket_document.dart';
import '../../domain/repositories/ticket_document_repository.dart';

/// Où en est le PDF officiel d'un billet.
enum TicketDocumentPhase { idle, downloading, ready, failed }

/// L'état du PDF d'**un** billet.
class TicketDocumentEntry extends Equatable {
  const TicketDocumentEntry({
    this.phase = TicketDocumentPhase.idle,
    this.document,
    this.error,
  });

  final TicketDocumentPhase phase;

  /// Le dernier document obtenu. Il reste affiché pendant un nouveau
  /// téléchargement, et après un échec réseau : le voyageur garde son
  /// billet.
  final TicketDocument? document;

  /// Renseigné quand [phase] vaut [TicketDocumentPhase.failed].
  final TicketDocumentError? error;

  bool get isDownloading => phase == TicketDocumentPhase.downloading;

  @override
  List<Object?> get props => [phase, document, error];
}

/// Couche PRESENTATION : les PDF officiels, billet par billet.
class TicketDocumentState extends Equatable {
  const TicketDocumentState([this.entries = const {}]);

  final Map<String, TicketDocumentEntry> entries;

  TicketDocumentEntry of(String ticketId) =>
      entries[ticketId] ?? const TicketDocumentEntry();

  TicketDocumentState _with(String ticketId, TicketDocumentEntry entry) =>
      TicketDocumentState({...entries, ticketId: entry});

  @override
  List<Object?> get props => [entries];
}

/// Les téléchargements de PDF officiels, pour toute l'application.
///
/// **Un seul cubit** — la liste, le détail et les billets du groupe lisent
/// le même état : un téléchargement lancé depuis la liste apparaît en cours
/// sur le détail, et aucun écran n'en relance un second pour le même billet.
class TicketDocumentCubit extends Cubit<TicketDocumentState> {
  TicketDocumentCubit(this._repository) : super(const TicketDocumentState());

  final TicketDocumentRepository _repository;

  /// Reprend le document déjà enregistré, sans réseau — c'est lui qui
  /// permet d'ouvrir son billet hors connexion.
  Future<void> restore(String ticketId) async {
    if (state.of(ticketId).phase != TicketDocumentPhase.idle) return;

    final document = await _repository.saved(ticketId);
    if (document == null || isClosed) return;

    // Un téléchargement a pu démarrer pendant la lecture du disque : il
    // l'emporte sur la copie locale.
    if (state.of(ticketId).phase != TicketDocumentPhase.idle) return;

    emit(
      state._with(
        ticketId,
        TicketDocumentEntry(
          phase: TicketDocumentPhase.ready,
          document: document,
        ),
      ),
    );
  }

  /// Télécharge le PDF officiel. Sans effet pendant qu'un téléchargement du
  /// même billet est en cours.
  Future<TicketDocument?> download(String ticketId) async {
    final current = state.of(ticketId);
    if (current.isDownloading) return null;

    emit(
      state._with(
        ticketId,
        TicketDocumentEntry(
          phase: TicketDocumentPhase.downloading,
          document: current.document,
        ),
      ),
    );

    final result = await _repository.download(ticketId);
    if (isClosed) return null;

    return result.fold(
      (error) {
        emit(
          state._with(
            ticketId,
            TicketDocumentEntry(
              phase: TicketDocumentPhase.failed,
              error: error,
              // Le serveur vient de refuser l'accès : l'écran ne doit plus
              // proposer la copie locale de ce billet.
              document: _keepsCopy(error) ? current.document : null,
            ),
          ),
        );
        return null;
      },
      (document) {
        emit(
          state._with(
            ticketId,
            TicketDocumentEntry(
              phase: TicketDocumentPhase.ready,
              document: document,
            ),
          ),
        );
        return document;
      },
    );
  }

  static bool _keepsCopy(TicketDocumentError error) => switch (error) {
    TicketDocumentError.sessionExpired ||
    TicketDocumentError.inaccessible => false,
    TicketDocumentError.network ||
    TicketDocumentError.unavailable ||
    TicketDocumentError.unexpected => true,
  };
}
