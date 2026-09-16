import 'package:equatable/equatable.dart';

/// Le PDF officiel est-il servi par l'API ?
///
/// `false` tant que MEZ-82 n'a pas livré `GET /api/v1/tickets/{uuid}/pdf` :
/// les écrans gardent alors le billet recomposé sur le téléphone, faute de
/// mieux. Le jour où la route existe, un build avec
/// `--dart-define=KORA_OFFICIAL_TICKET_PDF=true` bascule tous les écrans sur
/// le document du backend — et plus aucun ne recompose le billet.
///
/// Un interrupteur plutôt qu'un repli automatique sur 404 : un 404 veut
/// aussi dire « ce billet n'est pas le vôtre », et fabriquer un PDF à la
/// place masquerait exactement l'erreur que le contrôle d'accès exprime.
const bool kOfficialTicketPdfEnabled = bool.fromEnvironment(
  'KORA_OFFICIAL_TICKET_PDF',
);

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

  /// 5xx, ou une réponse qui n'est pas un PDF : la génération côté backend
  /// est indisponible pour l'instant.
  unavailable,

  /// Tout le reste — un stockage plein, une réponse inattendue.
  unexpected,
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
