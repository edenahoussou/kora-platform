import '../../ops/domain/entities/depart.dart';
import '../../ops/domain/entities/passenger.dart';
import '../../ops/domain/entities/stop.dart';

/// Arrêts où [passenger] peut légitimement signaler une descente
/// anticipée sur [depart] — miroir côté app de `ProcessSeatReleaseAction.
/// isStopCompatible` côté backend : entre l'embarquement (inclus) et la
/// destination initiale du billet (exclue — sinon ce n'est pas une
/// descente "anticipée", juste la fin normale du trajet).
///
/// Confort d'UX uniquement — **le serveur reste seul arbitre** : si
/// [Depart.stops] ou les UUID d'arrêt du passager manquent (manifeste
/// chargé avant MEZ-87, ou pas encore rafraîchi), retombe sur la liste
/// complète des arrêts plutôt que d'en priver l'agent ; un arrêt mal
/// choisi sera de toute façon rejeté par le serveur
/// (`stop_not_compatible`).
List<Stop> compatibleStopsFor(Depart depart, Passenger passenger) {
  final List<Stop> stops = depart.stops;
  if (stops.isEmpty) return stops;

  final String? originUuid = passenger.originStopUuid;
  final String? destinationUuid = passenger.destinationStopUuid;
  if (originUuid == null || destinationUuid == null) return stops;

  final int originIndex =
      stops.indexWhere((Stop s) => s.uuid == originUuid);
  final int destinationIndex =
      stops.indexWhere((Stop s) => s.uuid == destinationUuid);
  if (originIndex == -1 ||
      destinationIndex == -1 ||
      originIndex >= destinationIndex) {
    return stops;
  }

  return stops.sublist(originIndex, destinationIndex);
}
