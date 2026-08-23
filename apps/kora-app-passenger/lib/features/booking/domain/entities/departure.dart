import 'package:equatable/equatable.dart';

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
/// L'heure d'arrivée n'est pas stockée : elle se déduit de [departsAt] et
/// de la durée de la ligne (voir [arrivesAt]). Une seule source de vérité,
/// donc aucun risque d'incohérence entre les deux champs.
class Departure extends Equatable {
  const Departure({
    required this.id,
    required this.line,
    required this.departsAt,
    required this.comfort,
    required this.price,
    required this.seatsTotal,
    required this.seatsAvailable,
    this.amenities = const [],
    this.busLabel,
  });

  final String id;
  final Line line;

  /// Date **et** heure de départ.
  final DateTime departsAt;

  final BusComfort comfort;

  /// Prix pour un voyageur sur la totalité du trajet.
  final Price price;

  final int seatsTotal;
  final int seatsAvailable;

  /// Services à bord (`Wi-Fi`, `Climatisation`, `USB`...).
  final List<String> amenities;

  /// Le véhicule réellement affecté au départ (`Mercedes O500`), quand
  /// l'API le connaît.
  ///
  /// Distinct de [comfort], qui est une **gamme commerciale** KORA : tant
  /// que l'API n'expose pas la gamme, les écrans affichent ce libellé-ci
  /// plutôt qu'une gamme devinée. `null` sur les données mockées, qui
  /// n'ont que la gamme.
  final String? busLabel;

  /// Ce qu'on affiche dans le badge du véhicule : le bus réel s'il est
  /// connu, la gamme sinon.
  String get vehicleLabel => busLabel ?? comfort.label;

  DateTime get arrivesAt => departsAt.add(line.duration);

  bool get isSoldOut => seatsAvailable == 0;

  @override
  List<Object?> get props => [
    id,
    line,
    departsAt,
    comfort,
    price,
    seatsTotal,
    seatsAvailable,
    amenities,
    busLabel,
  ];
}
