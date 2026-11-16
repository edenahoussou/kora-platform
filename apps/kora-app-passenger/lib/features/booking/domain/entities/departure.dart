import 'package:equatable/equatable.dart';

import 'amenity.dart';
import 'line.dart';
import 'price.dart';

/// Les gammes de bus proposées par KORA EXPRESS. Le libellé est celui
/// affiché dans le badge doré de la carte de départ (`DepartureCard` du
/// design system).
enum BusComfort {
  classique('KORA Classique'),
  confort('KORA Confort'),
  vip('KORA VIP');

  const BusComfort(this.label);

  final String label;
}

/// Couche DOMAIN : un départ précis sur une [Line] — c'est ce que
/// l'utilisateur réserve.
///
/// L'heure d'arrivée est celle du serveur quand il la donne, et se
/// déduit de [departsAt] et de la durée de la ligne sinon (voir
/// [arrivesAt]).
class Departure extends Equatable {
  const Departure({
    required this.id,
    required this.line,
    required this.departsAt,
    this.arrival,
    required this.comfort,
    required this.price,
    required this.seatsTotal,
    required this.seatsAvailable,
    this.originStopId,
    this.destinationStopId,
    this.originCountry,
    this.destinationCountry,
    this.amenities = const [],
    this.busLabel,
  });

  final String id;
  final Line line;

  /// Date **et** heure de départ.
  final DateTime departsAt;

  /// L'heure d'arrivée annoncée par le serveur pour ce tronçon.
  ///
  /// La durée de la ligne ne la donne pas toujours : elle vaut pour la
  /// ligne entière, pas pour le tronçon retenu. Nul sur les données
  /// mockées — voir [arrivesAt].
  final DateTime? arrival;

  final BusComfort comfort;

  /// Prix pour un voyageur sur la totalité du trajet.
  final Price price;

  final int seatsTotal;
  final int seatsAvailable;

  /// Codes pays ISO des deux arrêts choisis (`BJ`, `TG`), quand l'API les
  /// donne. Voir [isInternational].
  final String? originCountry;
  final String? destinationCountry;

  /// Le voyage franchit une frontière.
  ///
  /// Se juge sur le **tronçon retenu**, jamais sur la ligne : L1 relie
  /// Cotonou à Abidjan en traversant quatre pays, et l'API la marque
  /// `internationale`, mais un Cotonou → Porto-Novo pris dessus ne quitte
  /// pas le Bénin. Réclamer un passeport pour ce trajet-là au prétexte que
  /// la ligne va plus loin serait absurde.
  ///
  /// Faute de pays connus — un départ mocké, une réponse ancienne — on
  /// répond `false` : le formulaire simple laisse partir, là où le
  /// formulaire international bloquerait sur des champs que personne ne
  /// peut remplir.
  bool get isInternational {
    final origin = originCountry;
    final destination = destinationCountry;
    if (origin == null || destination == null) return false;

    return origin.toUpperCase() != destination.toUpperCase();
  }

  /// Services à bord, tels que le serveur les annonce pour le bus affecté.
  ///
  /// Vide tant qu'aucun bus n'est assigné au départ — et les écrans se
  /// taisent alors, plutôt que d'inventer un confort.
  final List<Amenity> amenities;

  /// Le véhicule réellement affecté au départ (`Mercedes O500`), quand
  /// l'API le connaît.
  ///
  /// Distinct de [comfort], qui est une **gamme commerciale** KORA : tant
  /// que l'API n'expose pas la gamme, les écrans affichent ce libellé-ci
  /// plutôt qu'une gamme devinée. `null` sur les données mockées, qui
  /// n'ont que la gamme.
  final String? busLabel;

  /// Arrêts de montée et de descente du tronçon cherché, tels que l'API
  /// les identifie.
  ///
  /// Le plan des sièges en dépend : la disponibilité d'une place se
  /// calcule **par tronçon** — une place vendue de Lomé à Abidjan reste
  /// libre de Cotonou à Lomé. `null` sur les données mockées, qui ne
  /// connaissent pas les arrêts.
  final String? originStopId;
  final String? destinationStopId;

  /// Ce qu'on affiche dans le badge du véhicule : le bus réel s'il est
  /// connu, la gamme sinon.
  String get vehicleLabel => busLabel ?? comfort.label;

  /// L'heure d'arrivée : celle du serveur, ou la durée de ligne ajoutée
  /// au départ quand il ne la dit pas.
  DateTime get arrivesAt => arrival ?? departsAt.add(line.duration);

  bool get isSoldOut => seatsAvailable == 0;

  @override
  List<Object?> get props => [
    id,
    originStopId,
    destinationStopId,
    originCountry,
    destinationCountry,
    line,
    departsAt,
    arrival,
    comfort,
    price,
    seatsTotal,
    seatsAvailable,
    amenities,
    busLabel,
  ];
}
