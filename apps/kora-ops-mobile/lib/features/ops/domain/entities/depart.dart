import 'package:equatable/equatable.dart';
import 'crew.dart';
import 'stop.dart';

/// Un départ (course) que l'agent peut avoir à gérer — sélectionné depuis
/// [TourneePage], affiché en résumé sur `AccueilPage` et utilisé pour
/// filtrer le Manifeste / la Descente anticipée.
///
/// [route]/[date]/[heure]/[vehiculeImmat]/[totalPlaces]/[placesOccupees]
/// sont les champs "prêts à afficher" déjà consommés par les écrans
/// existants (MEZ-11) — volontairement inchangés pour ne rien casser.
/// [lineCode]/[stops]/[crew]/[status]/[manifestVersion] sont additionnels
/// (MEZ-92, données réelles) : `null`/vide tant qu'ils n'ont pas été
/// chargés (ex. ligne issue de la liste `GET /ops/departures`, qui ne
/// renvoie pas les arrêts — voir `DepartureDtos.fromIndexRow`).
class Depart extends Equatable {
  const Depart({
    required this.id,
    required this.route,
    required this.date,
    required this.heure,
    required this.vehiculeImmat,
    required this.totalPlaces,
    required this.placesOccupees,
    this.lineCode,
    this.lineName,
    this.status,
    this.stops = const <Stop>[],
    this.crew,
    this.manifestVersion,
  });

  final String id;

  /// Ex : "Cotonou → Porto-Novo" (issu des arrêts, voir [stops]) ou, à
  /// défaut, le nom de la ligne telle quelle (ex : "Cotonou - Lomé").
  final String route;

  /// Ex : "14/08/2026"
  final String date;

  /// Ex : "07:30"
  final String heure;

  /// Ex : "AB-1234-RB"
  final String vehiculeImmat;

  final int totalPlaces;

  /// Nombre de passagers à embarquer — non fourni par
  /// `GET /ops/departures` (coûterait un chargement du manifeste par
  /// ligne de liste) ; à 0 tant que le manifeste du départ n'a pas été
  /// chargé (voir `ManifesteCubit`, qui est la source de vérité une fois
  /// le départ sélectionné).
  final int placesOccupees;

  final String? lineCode;
  final String? lineName;

  /// Ex : "scheduled", "boarding", "departed", "arrived" — voir
  /// `DepartureStatus` côté backend.
  final String? status;

  final List<Stop> stops;
  final Crew? crew;
  final String? manifestVersion;

  Depart copyWith({
    String? route,
    int? placesOccupees,
    String? lineCode,
    String? lineName,
    String? status,
    List<Stop>? stops,
    Crew? crew,
    String? manifestVersion,
  }) {
    return Depart(
      id: id,
      route: route ?? this.route,
      date: date,
      heure: heure,
      vehiculeImmat: vehiculeImmat,
      totalPlaces: totalPlaces,
      placesOccupees: placesOccupees ?? this.placesOccupees,
      lineCode: lineCode ?? this.lineCode,
      lineName: lineName ?? this.lineName,
      status: status ?? this.status,
      stops: stops ?? this.stops,
      crew: crew ?? this.crew,
      manifestVersion: manifestVersion ?? this.manifestVersion,
    );
  }

  @override
  List<Object?> get props => <Object?>[
        id,
        route,
        date,
        heure,
        vehiculeImmat,
        totalPlaces,
        placesOccupees,
        lineCode,
        lineName,
        status,
        stops,
        crew,
        manifestVersion,
      ];
}
