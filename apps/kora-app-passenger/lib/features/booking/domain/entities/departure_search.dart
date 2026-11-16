import 'package:equatable/equatable.dart';

import 'itinerary.dart';

/// Couche DOMAIN : ce que rend une recherche de trajets.
///
/// Chaque entrée est un [Itinerary] : un trajet direct, ou un voyage à
/// correspondance dont les tronçons se réservent ensemble.
///
/// Un aller simple n'a qu'un sens ; un aller-retour en a deux, que le
/// serveur rend dans deux listes séparées
/// (`{outbound, inbound}`) parce qu'ils ne se choisissent pas ensemble :
/// le voyageur prend son aller, puis son retour.
///
/// [inbound] vaut `null` sur un aller simple et `[]` sur un aller-retour
/// dont la date de retour n'est desservie par rien. La distinction porte :
/// dans le premier cas il n'y a pas d'étape de retour du tout, dans le
/// second il y en a une, et elle est vide — ce qu'il faut dire au
/// voyageur plutôt que de le laisser devant une liste muette.
class DepartureSearch extends Equatable {
  const DepartureSearch({required this.outbound, this.inbound});

  const DepartureSearch.oneWay(List<Itinerary> itineraries)
    : this(outbound: itineraries);

  /// Les voyages de l'aller.
  final List<Itinerary> outbound;

  /// Les voyages du retour, ou nul si aucun retour n'a été demandé.
  final List<Itinerary>? inbound;

  bool get isRoundTrip => inbound != null;

  /// Vrai quand la recherche n'a rien à proposer, dans un sens ou dans
  /// l'autre.
  ///
  /// Un aller-retour dont un seul sens est desservi n'est pas réservable :
  /// le devis porte les deux, et le serveur refuse un retour qui ne
  /// revient pas au point de départ.
  bool get isEmpty => outbound.isEmpty || (inbound?.isEmpty ?? false);

  @override
  List<Object?> get props => [outbound, inbound];
}
