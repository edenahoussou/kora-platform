import 'package:equatable/equatable.dart';

import 'city.dart';
import 'price.dart';

/// Couche DOMAIN : un tronçon d'une [Line], entre deux arrêts consécutifs.
///
/// C'est ce qui permet à l'écran de détail d'afficher les étapes du trajet
/// avec leur heure de passage, et le prix de chaque portion pour qui
/// descend avant le terminus.
///
/// Les horaires sont exprimés en **décalage depuis le départ de la ligne**
/// et non en heures absolues : une ligne est indépendante de la date, ce
/// sont les [Departure] qui la datent. L'écran ajoute simplement ces
/// décalages à l'heure de départ réelle.
class TripSegment extends Equatable {
  const TripSegment({
    required this.origin,
    required this.destination,
    required this.departureOffset,
    required this.arrivalOffset,
    required this.distanceKm,
    required this.price,
  });

  final City origin;
  final City destination;

  /// Temps écoulé entre le départ de la ligne et le départ de ce tronçon.
  final Duration departureOffset;

  /// Idem pour l'arrivée au bout de ce tronçon.
  final Duration arrivalOffset;

  final int distanceKm;

  /// Tarif de cette portion seule.
  final Price price;

  Duration get duration => arrivalOffset - departureOffset;

  @override
  List<Object?> get props => [
    origin,
    destination,
    departureOffset,
    arrivalOffset,
    distanceKm,
    price,
  ];
}
