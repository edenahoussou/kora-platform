import 'package:equatable/equatable.dart';

import 'city.dart';
import 'departure.dart';
import 'price.dart';

/// Couche DOMAIN : un voyage d'un bout à l'autre.
///
/// Un seul tronçon quand une ligne mène directement d'une ville à
/// l'autre ; plusieurs quand il faut changer de bus en route — Porto-Novo
/// → Parakou se fait en descendant du Porto-Novo–Lomé à Cotonou, puis en
/// reprenant le Cotonou–Parakou.
///
/// C'est l'unité que rend `GET bus/itineraries`, et celle que le tunnel
/// porte jusqu'au billet : **une correspondance reste une réservation,
/// un paiement et un billet**. Le serveur bloque les sièges des deux bus
/// ensemble (`seat_assignments[].leg_order`) et n'émet qu'un billet par
/// passager et par réservation.
///
/// Les heures et le prix viennent du serveur, pas d'un calcul local : la
/// durée d'une correspondance comprend l'attente à l'arrêt de
/// changement, qu'aucune addition de durées de lignes ne donnerait.
class Itinerary extends Equatable {
  const Itinerary({
    required this.legs,
    required this.total,
    required this.departsAt,
    required this.arrivesAt,
    this.connectionMargin,
  });

  /// Le voyage d'un seul tronçon, pour les sources qui n'en connaissent
  /// pas d'autres : jeu de démonstration, voyage reconstruit d'un billet.
  factory Itinerary.direct(Departure departure) => Itinerary(
    legs: [departure],
    total: departure.price,
    departsAt: departure.departsAt,
    arrivesAt: departure.arrivesAt,
  );

  /// Les tronçons dans l'ordre du voyage, au moins un.
  final List<Departure> legs;

  /// Prix du voyage entier pour un voyageur, correspondances comprises.
  final Price total;

  /// Départ du premier tronçon et arrivée du dernier, tels que le serveur
  /// les donne.
  final DateTime departsAt;
  final DateTime arrivesAt;

  /// La marge d'exploitation que le serveur garde entre deux bus — en
  /// dessous, il ne propose pas la correspondance. `null` sur un trajet
  /// direct, et quand le serveur ne la dit pas.
  final Duration? connectionMargin;

  Departure get first => legs.first;
  Departure get last => legs.last;

  bool get isDirect => legs.length == 1;

  /// Le nombre de changements de bus : `0` pour un trajet direct.
  int get connectionsCount => legs.length - 1;

  /// Les villes où l'on change de bus, dans l'ordre.
  List<City> get connectionCities => [
    for (final leg in legs.skip(1)) leg.line.origin,
  ];

  /// Durée réelle du voyage, attentes de correspondance comprises.
  Duration get duration => arrivesAt.difference(departsAt);

  City get origin => first.line.origin;
  City get destination => last.line.destination;

  /// Les places restantes du tronçon le plus rempli : c'est lui qui
  /// limite le voyage, puisqu'il faut une place sur chacun.
  int get seatsAvailable =>
      legs.map((leg) => leg.seatsAvailable).reduce((a, b) => a < b ? a : b);

  /// Complet dès qu'un seul tronçon l'est : le voyage entier ne se vend
  /// plus.
  bool get isSoldOut => legs.any((leg) => leg.isSoldOut);

  /// Le voyage franchit une frontière, sur l'un ou l'autre tronçon.
  ///
  /// Un seul tronçon international suffit : c'est le passeport du voyage
  /// entier qui sera demandé au formulaire.
  bool get isInternational => legs.any((leg) => leg.isInternational);

  /// Le temps passé à attendre à chaque correspondance, dans l'ordre.
  ///
  /// Se lit entre l'arrivée d'un tronçon et le départ du suivant, tels
  /// que le serveur les donne.
  List<Duration> get connectionWaits => [
    for (var index = 1; index < legs.length; index++)
      legs[index].departsAt.difference(legs[index - 1].arrivesAt),
  ];

  /// Identifiant stable du voyage : les départs de ses tronçons.
  ///
  /// Deux itinéraires peuvent partir à la même heure du même endroit et
  /// se séparer ensuite ; c'est la suite complète qui les distingue.
  String get id => legs.map((leg) => leg.id).join('+');

  @override
  List<Object?> get props => [
    legs,
    total,
    departsAt,
    arrivesAt,
    connectionMargin,
  ];
}
