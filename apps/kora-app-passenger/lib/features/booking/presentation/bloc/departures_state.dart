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
    this.itineraries = const [],
    this.sortField = DepartureSortField.hour,
    this.sortDescending = false,
    this.timeSlots = const {},
    this.isRefreshing = false,
    this.errorMessage,
    this.refreshError,
    this.returnItineraries,
  });

  final DeparturesStatus status;

  /// Renseigné dès que les identifiants de l'URL ont été résolus, donc
  /// **avant** la fin de la recherche : l'écran de chargement peut déjà
  /// annoncer le trajet cherché.
  final SearchCriteria? criteria;

  /// Tous les voyages rendus par la recherche, filtres et tri non
  /// appliqués — voir [visibleItineraries]. Sur un aller-retour, ceux de
  /// l'aller.
  ///
  /// Un voyage est direct ou à correspondance : les deux se listent sur
  /// le même écran, et c'est la carte qui dit lequel on regarde.
  final List<Itinerary> itineraries;

  /// Les voyages du retour, ou `null` sur un aller simple.
  ///
  /// Rendus par la même recherche que l'aller — le serveur les donne
  /// ensemble — mais listés sur leur propre écran : le voyageur choisit
  /// son aller, puis son retour. Une liste vide n'est pas `null` : il y a
  /// bien une étape de retour, et elle n'a rien à proposer.
  final List<Itinerary>? returnItineraries;

  bool get isRoundTrip => returnItineraries != null;

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
  bool get isEmpty => status == DeparturesStatus.success && itineraries.isEmpty;

  /// Des départs existent, mais les filtres les écartent tous. Un cas très
  /// différent de [isEmpty] : ici la liaison est desservie, c'est la
  /// demande qui est trop étroite — et la sortie est de relâcher les
  /// filtres, pas de changer de date.
  bool get isFilteredEmpty =>
      status == DeparturesStatus.success &&
      itineraries.isNotEmpty &&
      visibleItineraries.isEmpty;

  bool get hasFilters => timeSlots.isNotEmpty;

  /// Les voyages filtrés puis ordonnés — ce que l'écran affiche.
  ///
  /// Les voyages complets ferment toujours la marche, quel que soit le
  /// tri **et son sens** : les proposer en tête n'aiderait personne, et
  /// inverser le tri ne doit pas les faire remonter. À tri égal, le
  /// direct passe devant la correspondance — c'est l'ordre que le
  /// serveur applique lui aussi par défaut.
  List<Itinerary> get visibleItineraries {
    final visible = itineraries.where(_matchesFilters).toList();

    visible.sort((a, b) {
      if (a.isSoldOut != b.isSoldOut) return a.isSoldOut ? 1 : -1;

      final comparison = switch (sortField) {
        DepartureSortField.hour => a.departsAt.compareTo(b.departsAt),
        DepartureSortField.price => a.total.amount.compareTo(b.total.amount),
        // La durée du voyage entier, attentes de correspondance
        // comprises : additionner les durées de lignes mettrait une
        // correspondance de trois heures devant un direct.
        DepartureSortField.duration => a.duration.compareTo(b.duration),
      };

      if (comparison != 0) return sortDescending ? -comparison : comparison;

      return a.connectionsCount.compareTo(b.connectionsCount);
    });

    return visible;
  }

  bool _matchesFilters(Itinerary itinerary) {
    if (timeSlots.isEmpty) return true;
    return timeSlots.any((slot) => slot.contains(itinerary.departsAt));
  }

  /// `3 départs disponibles` — titre de l'écran, qui ne compte que les
  /// départs réellement réservables **et retenus par les filtres** : un
  /// titre qui annoncerait 4 départs au-dessus d'une liste filtrée à 2
  /// serait un mensonge.
  int get availableCount =>
      visibleItineraries.where((itinerary) => !itinerary.isSoldOut).length;

  /// Tous les départs réservables de la liaison, **filtres ignorés**.
  ///
  /// C'est le chiffre du titre. Il ne ment pas au-dessus d'une liste
  /// filtrée parce que le récapitulatif, juste avant les cartes, dit
  /// aussitôt combien il en reste et pourquoi : « 3 départs disponibles »
  /// puis « 2 départs ce matin ». Sans cette seconde ligne, il faudrait
  /// que le titre suive les filtres — voir [availableCount].
  int get totalAvailableCount =>
      itineraries.where((itinerary) => !itinerary.isSoldOut).length;

  /// Le prix le plus bas et le plus haut des départs affichés, pour le
  /// récapitulatif. `null` quand la liste est vide.
  (Price, Price)? get visiblePriceRange {
    final prices =
        visibleItineraries
            .where((itinerary) => !itinerary.isSoldOut)
            .map((itinerary) => itinerary.total)
            .toList()
          ..sort((a, b) => a.amount.compareTo(b.amount));

    if (prices.isEmpty) return null;
    return (prices.first, prices.last);
  }

  DeparturesState copyWith({
    DeparturesStatus? status,
    SearchCriteria? criteria,
    List<Itinerary>? itineraries,
    DepartureSortField? sortField,
    bool? sortDescending,
    Set<DepartureTimeSlot>? timeSlots,
    bool? isRefreshing,
    String? errorMessage,
    String? refreshError,
    List<Itinerary>? returnItineraries,
  }) {
    return DeparturesState(
      status: status ?? this.status,
      criteria: criteria ?? this.criteria,
      itineraries: itineraries ?? this.itineraries,
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
      returnItineraries: returnItineraries ?? this.returnItineraries,
    );
  }

  @override
  List<Object?> get props => [
    returnItineraries,
    status,
    criteria,
    itineraries,
    sortField,
    sortDescending,
    timeSlots,
    isRefreshing,
    errorMessage,
    refreshError,
  ];
}
