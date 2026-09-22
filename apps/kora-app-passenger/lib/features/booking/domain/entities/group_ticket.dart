import 'package:equatable/equatable.dart';

/// Où en est un billet émis, du point de vue du contrôle.
enum GroupTicketStatus {
  /// Émis et utilisable.
  issued,

  /// Le voyageur est monté : le QR a été scanné.
  boarded,

  /// Annulé — le billet ne vaut plus rien au contrôle.
  cancelled,

  /// Un statut que cette version de l'app ne connaît pas encore.
  ///
  /// Rendu tel quel plutôt que traduit en « annulé » : un statut ajouté
  /// côté serveur ne doit pas faire passer un billet valable pour un
  /// billet mort.
  unknown;

  static GroupTicketStatus fromWire(String wire) => switch (wire) {
    'issued' => issued,
    'boarded' => boarded,
    'cancelled' => cancelled,
    _ => unknown,
  };

  /// Le billet peut encore servir à embarquer.
  bool get isUsable => this == issued;
}

/// Couche DOMAIN : le billet d'**un** voyageur du groupe.
///
/// Distinct de `Ticket`, qui décrit un voyage dans l'onglet Voyages : ici
/// chaque voyageur a le sien, avec son nom, son siège et son QR. C'est
/// toute la raison d'être de l'écran des billets du groupe — dix personnes,
/// dix codes, et la validation de l'un n'affecte pas les autres.
class GroupTicket extends Equatable {
  const GroupTicket({
    required this.id,
    required this.shortCode,
    required this.status,
    this.qrCode = '',
    this.passengerName = '',
    this.seatCode = '',
    this.isStale = false,
  });

  final String id;

  /// Référence courte imprimée sur le billet (`KE-20260804-78342`).
  final String shortCode;

  final GroupTicketStatus status;

  /// La charge à encoder dans le QR — un identifiant, pas une image : c'est
  /// l'app qui le dessine.
  final String qrCode;

  /// Prénom et nom du voyageur, tels que le manifeste les porte. Vide si
  /// le serveur n'a pas rattaché de voyageur au billet.
  final String passengerName;

  /// `4A`. Vide tant qu'aucun siège n'est affecté.
  final String seatCode;

  /// Lu sur le téléphone, faute de réseau : le billet reste valable — le
  /// QR est signé par le serveur — mais l'écran peut le dire.
  final bool isStale;

  /// Ce qui identifie le billet dans la liste : le nom du voyageur, ou à
  /// défaut sa référence — jamais une ligne vide.
  String get label => passengerName.isNotEmpty ? passengerName : shortCode;

  /// Le même billet, mais sorti de la copie locale.
  GroupTicket get stale => GroupTicket(
    id: id,
    shortCode: shortCode,
    status: status,
    qrCode: qrCode,
    passengerName: passengerName,
    seatCode: seatCode,
    isStale: true,
  );

  @override
  List<Object?> get props => [
    id,
    shortCode,
    status,
    qrCode,
    passengerName,
    seatCode,
    isStale,
  ];
}
