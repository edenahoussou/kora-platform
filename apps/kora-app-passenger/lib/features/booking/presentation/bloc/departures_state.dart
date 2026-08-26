part of 'departures_bloc.dart';

enum DeparturesStatus { loading, success, failure }

/// Sur quoi la liste des départs est ordonnée.
///
/// Le libellé n'est pas porté ici : ces trois valeurs sont traduites
/// (`departures_sort_*`), et une constante figée dans l'enum aurait
/// affiché « Heure » au milieu d'un écran anglais.
enum DepartureSortField { hour, price, duration }

/// Le moment de la journée d'un départ — le filtre `Matin / Après-midi /
/// Soir`.
///
/// Les bornes sont celles du langage courant, pas des tiers de journée
/// mathématiques : un départ à 12:30 est « l'après-midi », un départ à
/// 18:00 est « le soir ». La nuit (00:00–04:59) reste rattachée au matin,
/// faute de quoi les départs de nuit deviendraient invisibles au filtre.
enum DepartureTimeSlot {
  morning(0, 12),
  afternoon(12, 18),
  evening(18, 24);

  const DepartureTimeSlot(this.fromHour, this.toHour);

  /// Heure de début incluse.
  final int fromHour;

  /// Heure de fin exclue.
  final int toHour;

  bool contains(DateTime moment) =>
      moment.hour >= fromHour && moment.hour < toHour;
}

/// Couche PRESENTATION : tout ce dont l'écran des départs a besoin.
class DeparturesState extends Equatable {
  const DeparturesState({
    this.status = DeparturesStatus.loading,
    this.criteria,
    this.departures = const [],
    this.sortField = DepartureSortField.hour,
    this.sortDescending = false,
    this.timeSlots = const {},
    this.isRefreshing = false,
    this.errorMessage,
    this.refreshError,
  });

  final DeparturesStatus status;

  /// Renseigné dès que les identifiants de l'URL ont été résolus, donc
  /// **avant** la fin de la recherche : l'écran de chargement peut déjà
  /// annoncer le trajet cherché.
  final SearchCriteria? criteria;

  /// Tous les départs rendus par la recherche, filtres et tri non
  /// appliqués — voir [visibleDepartures].
  final List<Departure> departures;

  final DepartureSortField sortField;

  /// Le sens du tri : croissant par défaut (le plus tôt, le moins cher,
  /// le plus court d'abord), inversé quand l'utilisateur retape la puce
  /// déjà active.
  final bool sortDescending;

  /// Les créneaux retenus. Vide = aucun filtre, donc **tous** les
  /// départs : un ensemble vide ne veut pas dire « aucun créneau ne
  /// convient », il veut dire « on n'a rien demandé ».
  final Set<DepartureTimeSlot> timeSlots;

  /// Une actualisation tirée depuis la liste est en cours. Distinct de
  /// [DeparturesStatus.loading] : les résultats actuels restent affichés
  /// sous l'indicateur, là où `loading` les remplace par des squelettes.
  final bool isRefreshing;

  final String? errorMessage;

  /// L'échec d'une actualisation, à signaler sans effacer la liste déjà
  /// affichée. Consommé par un message temporaire, puis remis à `null`.
  final String? refreshError;

  /// Aucun départ sur cette liaison ce jour-là. C'est un succès, pas une
  /// erreur : l'écran a un état dédié qui propose de changer la date ou le
  /// trajet.
  bool get isEmpty => status == DeparturesStatus.success && departures.isEmpty;

  /// Des départs existent, mais les filtres les écartent tous. Un cas très
  /// différent de [isEmpty] : ici la liaison est desservie, c'est la
  /// demande qui est trop étroite — et la sortie est de relâcher les
  /// filtres, pas de changer de date.
  bool get isFilteredEmpty =>
      status == DeparturesStatus.success &&
      departures.isNotEmpty &&
      visibleDepartures.isEmpty;

  bool get hasFilters => timeSlots.isNotEmpty;

  /// Les départs filtrés puis ordonnés — ce que l'écran affiche.
  ///
  /// Les départs complets ferment toujours la marche, quel que soit le
  /// tri **et son sens** : les proposer en tête n'aiderait personne, et
  /// inverser le tri ne doit pas les faire remonter.
  List<Departure> get visibleDepartures {
    final visible = departures.where(_matchesFilters).toList();

    visible.sort((a, b) {
      if (a.isSoldOut != b.isSoldOut) return a.isSoldOut ? 1 : -1;

      final comparison = switch (sortField) {
        DepartureSortField.hour => a.departsAt.compareTo(b.departsAt),
        DepartureSortField.price => a.price.amount.compareTo(b.price.amount),
        DepartureSortField.duration => a.line.duration.compareTo(
          b.line.duration,
        ),
      };

      return sortDescending ? -comparison : comparison;
    });

    return visible;
  }

  bool _matchesFilters(Departure departure) {
    if (timeSlots.isEmpty) return true;
    return timeSlots.any((slot) => slot.contains(departure.departsAt));
  }

  /// `3 départs disponibles` — titre de l'écran, qui ne compte que les
  /// départs réellement réservables **et retenus par les filtres** : un
  /// titre qui annoncerait 4 départs au-dessus d'une liste filtrée à 2
  /// serait un mensonge.
  int get availableCount =>
      visibleDepartures.where((departure) => !departure.isSoldOut).length;

  /// Tous les départs réservables de la liaison, **filtres ignorés**.
  ///
  /// C'est le chiffre du titre. Il ne ment pas au-dessus d'une liste
  /// filtrée parce que le récapitulatif, juste avant les cartes, dit
  /// aussitôt combien il en reste et pourquoi : « 3 départs disponibles »
  /// puis « 2 départs ce matin ». Sans cette seconde ligne, il faudrait
  /// que le titre suive les filtres — voir [availableCount].
  int get totalAvailableCount =>
      departures.where((departure) => !departure.isSoldOut).length;

  /// Le prix le plus bas et le plus haut des départs affichés, pour le
  /// récapitulatif. `null` quand la liste est vide.
  (Price, Price)? get visiblePriceRange {
    final prices =
        visibleDepartures
            .where((departure) => !departure.isSoldOut)
            .map((departure) => departure.price)
            .toList()
          ..sort((a, b) => a.amount.compareTo(b.amount));

    if (prices.isEmpty) return null;
    return (prices.first, prices.last);
  }

  DeparturesState copyWith({
    DeparturesStatus? status,
    SearchCriteria? criteria,
    List<Departure>? departures,
    DepartureSortField? sortField,
    bool? sortDescending,
    Set<DepartureTimeSlot>? timeSlots,
    bool? isRefreshing,
    String? errorMessage,
    String? refreshError,
  }) {
    return DeparturesState(
      status: status ?? this.status,
      criteria: criteria ?? this.criteria,
      departures: departures ?? this.departures,
      sortField: sortField ?? this.sortField,
      sortDescending: sortDescending ?? this.sortDescending,
      timeSlots: timeSlots ?? this.timeSlots,
      isRefreshing: isRefreshing ?? this.isRefreshing,
      errorMessage: errorMessage ?? this.errorMessage,
      // Pas de `??` ici : contrairement aux autres champs, celui-ci doit
      // pouvoir être **effacé**. Le message d'actualisation ratée est
      // consommé une fois puis disparaît ; le reconduire d'un état à
      // l'autre le ferait réapparaître au moindre changement de tri.
      refreshError: refreshError,
    );
  }

  @override
  List<Object?> get props => [
    status,
    criteria,
    departures,
    sortField,
    sortDescending,
    timeSlots,
    isRefreshing,
    errorMessage,
    refreshError,
  ];
}
