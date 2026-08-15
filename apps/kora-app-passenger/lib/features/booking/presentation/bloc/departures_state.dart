part of 'departures_bloc.dart';

enum DeparturesStatus { loading, success, failure }

/// Critère de tri des résultats — les deux puces demandées par le ticket.
enum DepartureSort {
  /// Départ le plus tôt d'abord.
  hour('Heure'),

  /// Prix le plus bas d'abord.
  price('Prix');

  const DepartureSort(this.label);

  final String label;
}

/// Couche PRESENTATION : tout ce dont l'écran des départs a besoin.
class DeparturesState extends Equatable {
  const DeparturesState({
    this.status = DeparturesStatus.loading,
    this.criteria,
    this.departures = const [],
    this.sort = DepartureSort.hour,
    this.errorMessage,
  });

  final DeparturesStatus status;

  /// Renseigné dès que les identifiants de l'URL ont été résolus, donc
  /// **avant** la fin de la recherche : l'écran de chargement peut déjà
  /// annoncer le trajet cherché.
  final SearchCriteria? criteria;

  final List<Departure> departures;
  final DepartureSort sort;
  final String? errorMessage;

  /// Aucun départ sur cette liaison ce jour-là. C'est un succès, pas une
  /// erreur : l'écran a un état dédié qui propose de changer la date ou le
  /// trajet.
  bool get isEmpty =>
      status == DeparturesStatus.success && departures.isEmpty;

  /// Les départs dans l'ordre demandé.
  ///
  /// Les départs complets ferment toujours la marche, quel que soit le
  /// tri : les proposer en tête n'aiderait personne.
  List<Departure> get sortedDepartures {
    final sorted = [...departures];
    sorted.sort((a, b) {
      if (a.isSoldOut != b.isSoldOut) return a.isSoldOut ? 1 : -1;

      return switch (sort) {
        DepartureSort.hour => a.departsAt.compareTo(b.departsAt),
        DepartureSort.price => a.price.amount.compareTo(b.price.amount),
      };
    });
    return sorted;
  }

  /// `3 départs disponibles` — titre de l'écran, qui ne compte que les
  /// départs réellement réservables.
  int get availableCount =>
      departures.where((departure) => !departure.isSoldOut).length;

  DeparturesState copyWith({
    DeparturesStatus? status,
    SearchCriteria? criteria,
    List<Departure>? departures,
    DepartureSort? sort,
    String? errorMessage,
  }) {
    return DeparturesState(
      status: status ?? this.status,
      criteria: criteria ?? this.criteria,
      departures: departures ?? this.departures,
      sort: sort ?? this.sort,
      errorMessage: errorMessage ?? this.errorMessage,
    );
  }

  @override
  List<Object?> get props => [
    status,
    criteria,
    departures,
    sort,
    errorMessage,
  ];
}
