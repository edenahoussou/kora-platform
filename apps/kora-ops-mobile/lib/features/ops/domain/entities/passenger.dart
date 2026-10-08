import 'package:equatable/equatable.dart';

/// Statut d'embarquement d'un passager sur le départ courant.
enum BoardingStatus { enAttente, embarque, refuse, descenduAnticipe }

extension BoardingStatusX on BoardingStatus {
  String get label => switch (this) {
        BoardingStatus.enAttente => 'En attente',
        BoardingStatus.embarque => 'Embarqué',
        BoardingStatus.refuse => 'Refusé',
        BoardingStatus.descenduAnticipe => 'Descente anticipée',
      };
}

/// Un passager du départ géré, tel qu'il apparaît dans le Manifeste.
class Passenger extends Equatable {
  const Passenger({
    required this.id,
    required this.nomComplet,
    required this.siege,
    required this.statut,
    required this.segmentOrigine,
    required this.segmentDestination,
    required this.ticketId,
    this.telephone,
    this.originStopUuid,
    this.destinationStopUuid,
    this.boardingEventUuid,
    this.nationalite,
    this.documentNumero,
    this.prenom,
    this.nom,
    this.email,
    this.documentType,
    this.dateNaissance,
    this.fievreJauneVaccine,
  });

  final String id;
  final String nomComplet;

  /// Ex : "12A"
  final String siege;

  final BoardingStatus statut;

  /// Ex : "Cotonou"
  final String segmentOrigine;

  /// Ex : "Porto-Novo"
  final String segmentDestination;

  final String ticketId;
  final String? telephone;

  /// UUID de l'arrêt d'origine/destination du segment (voir
  /// `OpsManifestRowResource.originStop`/`destinationStop`) — utilisés
  /// pour calculer les arrêts compatibles d'une descente anticipée (voir
  /// `compatibleStopsFor`, MEZ-87). `null` si le manifeste vient du cache
  /// local d'une version antérieure à MEZ-87 (redemandera un
  /// rafraîchissement).
  final String? originStopUuid;
  final String? destinationStopUuid;

  /// UUID du dernier `BoardingEvent` accepté pour ce billet sur ce départ
  /// — `null` si jamais embarqué. Reste renseigné même après une
  /// annulation (voir note côté backend, `OpsManifestRowResource`) : ne
  /// jamais s'y fier seul, toujours croiser avec `statut ==
  /// BoardingStatus.embarque` avant de proposer "Annuler l'embarquement".
  final String? boardingEventUuid;

  /// Code pays ISO 2 lettres (ex : "BJ") — champ de contrôle frontière,
  /// renvoyé par le serveur **uniquement** pour les départs sur ligne
  /// internationale (`OpsManifestRowResource`). `null` sinon, ou si le
  /// passager n'a pas renseigné cette information (MEZ-94).
  final String? nationalite;

  /// Numéro du document d'identité/voyage (`document.number`) — même
  /// périmètre que [nationalite]. Donnée sensible : jamais journalisée
  /// (voir `BorderManifestPrintCubit`).
  final String? documentNumero;

  /// Prénom / nom séparés, tels que saisis à la réservation — nécessaires
  /// au formulaire de correction (voir `OpsManifestRowResource.firstName`
  /// / `lastName`). `null` pour un cache antérieur à cette fonction : le
  /// formulaire retombe alors sur un découpage de [nomComplet].
  final String? prenom;
  final String? nom;

  /// Adresse email du passager (facultative).
  final String? email;

  /// Type de la pièce (`national_id`, `biometric_card`, `passport`,
  /// `consular_card`, `cip`) — champ de contrôle frontière, lignes
  /// internationales uniquement. Un passeport = `passport` + [documentNumero].
  final String? documentType;

  /// Date de naissance `yyyy-MM-dd` — lignes internationales uniquement.
  final String? dateNaissance;

  /// Vaccination fièvre jaune : `true` / `false`, `null` = non renseigné —
  /// lignes internationales uniquement.
  final bool? fievreJauneVaccine;

  Passenger copyWith({BoardingStatus? statut}) => Passenger(
        id: id,
        nomComplet: nomComplet,
        siege: siege,
        statut: statut ?? this.statut,
        segmentOrigine: segmentOrigine,
        segmentDestination: segmentDestination,
        ticketId: ticketId,
        telephone: telephone,
        originStopUuid: originStopUuid,
        destinationStopUuid: destinationStopUuid,
        boardingEventUuid: boardingEventUuid,
        nationalite: nationalite,
        documentNumero: documentNumero,
        prenom: prenom,
        nom: nom,
        email: email,
        documentType: documentType,
        dateNaissance: dateNaissance,
        fievreJauneVaccine: fievreJauneVaccine,
      );

  @override
  List<Object?> get props => <Object?>[
        id,
        nomComplet,
        siege,
        statut,
        segmentOrigine,
        segmentDestination,
        ticketId,
        telephone,
        originStopUuid,
        destinationStopUuid,
        boardingEventUuid,
        nationalite,
        documentNumero,
        prenom,
        nom,
        email,
        documentType,
        dateNaissance,
        fievreJauneVaccine,
      ];
}
