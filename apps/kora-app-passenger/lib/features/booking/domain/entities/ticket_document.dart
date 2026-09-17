import 'package:equatable/equatable.dart';

/// Pourquoi le PDF officiel n'a pas pu être obtenu.
///
/// Chaque cas appelle une suite différente, et l'écran doit pouvoir la
/// proposer sans lire un message serveur pour la deviner.
enum TicketDocumentError {
  /// Pas de réseau, ou le serveur n'a pas répondu à temps : réessayer.
  network,

  /// 401 : il faut se reconnecter avant de réessayer.
  sessionExpired,

  /// 403 ou 404 : le billet n'appartient pas à ce compte, ou n'existe pas.
  /// Réessayer n'y changera rien.
  inaccessible,

  /// 500, ou une réponse qui n'est pas un PDF : la génération a échoué
  /// côté backend. Temporaire — réessayer a un sens.
  unavailable,

  /// 422 `ticket_pdf_unavailable` : le billet n'est pas téléchargeable —
  /// annulé, remboursé, ou pas encore émis. Réessayer n'y changera rien.
  notDownloadable,

  /// Tout le reste — un stockage plein, une réponse inattendue.
  unexpected,
}

/// Un téléchargement refusé : la suite à proposer, et ce que le serveur a
/// répondu mot pour mot.
///
/// [kind] décide de l'écran — réessayer ou non, garder la copie ou non. Le
/// reste est le texte brut de la réponse : le PDF arrive en octets, et sans
/// cette lecture le message du backend (« Le PDF de ce billet n'est pas
/// disponible. ») disparaissait derrière un libellé générique.
class TicketDocumentFailure extends Equatable {
  const TicketDocumentFailure(
    this.kind, {
    this.status,
    this.code,
    this.message,
  });

  final TicketDocumentError kind;

  /// Le statut HTTP. `null` sans réponse (réseau coupé).
  final int? status;

  /// `error.code` du corps : `ticket_pdf_unavailable`,
  /// `ticket_pdf_generation_failed`…
  final String? code;

  /// `error.message` (ou `message`) du corps, tel quel.
  final String? message;

  /// La réponse du serveur en une ligne :
  /// `HTTP 422 · ticket_pdf_unavailable · Le PDF de ce billet n'est pas
  /// disponible.` — `null` quand il n'y a rien à rapporter.
  String? get serverDetail {
    final parts = [if (status != null) 'HTTP $status', ?code, ?message];
    return parts.isEmpty ? null : parts.join(' · ');
  }

  @override
  List<Object?> get props => [kind, status, code, message];
}

/// Couche DOMAIN : le PDF officiel d'un billet, enregistré sur le téléphone.
class TicketDocument extends Equatable {
  const TicketDocument({
    required this.ticketId,
    required this.fileName,
    required this.path,
    this.isStale = false,
  });

  /// L'UUID du billet — jamais son identifiant numérique interne.
  final String ticketId;

  /// Le nom de fichier fourni par le backend (`Content-Disposition`).
  final String fileName;

  /// Le fichier local, dans l'espace privé de l'application.
  final String path;

  /// Rendu depuis le téléphone parce que le réseau a manqué : le document
  /// est celui du dernier téléchargement réussi.
  final bool isStale;

  TicketDocument asStale() => TicketDocument(
    ticketId: ticketId,
    fileName: fileName,
    path: path,
    isStale: true,
  );

  @override
  List<Object?> get props => [ticketId, fileName, path, isStale];
}
