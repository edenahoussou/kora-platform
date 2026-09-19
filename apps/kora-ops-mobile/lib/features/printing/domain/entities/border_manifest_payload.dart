import 'package:equatable/equatable.dart';

/// Une ligne de passager du manifeste frontière (MEZ-94).
///
/// Volontairement sans prix, montant, téléphone ni statut d'embarquement :
/// uniquement ce que le contrôle frontière exige (nom, nationalité, n° de
/// document, destination, siège).
class BorderManifestPassengerLine extends Equatable {
  const BorderManifestPassengerLine({
    required this.nomComplet,
    required this.nationalite,
    required this.documentNumero,
    required this.destination,
    required this.siege,
  });

  final String nomComplet;

  /// Code pays ISO 2 lettres (ex : "BJ").
  final String nationalite;

  final String documentNumero;

  /// Ex : "Lomé"
  final String destination;

  /// Ex : "12A"
  final String siege;

  @override
  List<Object?> get props =>
      <Object?>[nomComplet, nationalite, documentNumero, destination, siege];
}

/// Données nécessaires à l'impression d'un exemplaire du manifeste
/// frontière 58 mm — voir `BorderManifestFormatter` pour le rendu.
///
/// Identique pour les 3 exemplaires : seul le numéro d'exemplaire (passé
/// séparément à `ThermalPrinter.printBorderManifest`) change.
class BorderManifestPayload extends Equatable {
  const BorderManifestPayload({
    required this.departureNumber,
    required this.departureDate,
    required this.departureTime,
    required this.lineCode,
    required this.route,
    required this.passengers,
    required this.printedAt,
    this.manifestVersion,
    this.manifestFetchedAt,
  });

  /// Numéro du départ imprimé (8 premiers caractères de l'UUID, en
  /// majuscules) — l'UUID complet ne tient pas sur 32 caractères.
  final String departureNumber;

  /// Ex : "14/08/2026"
  final String departureDate;

  /// Ex : "07:30"
  final String departureTime;

  /// Ex : "COT-LFW"
  final String lineCode;

  /// Ex : "Cotonou → Lomé"
  final String route;

  final List<BorderManifestPassengerLine> passengers;

  /// Horodatage de la demande d'impression (pied de page).
  final DateTime printedAt;

  /// Version serveur du manifeste imprimé — permet de retrouver, à la
  /// frontière comme au bureau, l'état exact du manifeste.
  final String? manifestVersion;

  /// Dernier téléchargement complet du manifeste (peut être ancien si le
  /// convoyeur imprime hors ligne).
  final DateTime? manifestFetchedAt;

  int get passengerCount => passengers.length;

  @override
  List<Object?> get props => <Object?>[
        departureNumber,
        departureDate,
        departureTime,
        lineCode,
        route,
        passengers,
        printedAt,
        manifestVersion,
        manifestFetchedAt,
      ];
}
