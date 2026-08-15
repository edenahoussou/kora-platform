part of 'home_bloc.dart';

/// Les 3 états de chargement de l'accueil. Le 4e état demandé par le
/// ticket — « empty » — n'en est pas un ici : ne pas avoir de voyage à
/// venir est un cas de succès parfaitement normal ([HomeState.nextTrip] à
/// `null`), pas une absence de données. C'est la carte « Prochain voyage »
/// qui affiche alors sa version vide.
enum HomeStatus { loading, success, failure }

/// Couche PRESENTATION : tout ce dont l'écran d'accueil a besoin pour se
/// dessiner, à un instant donné.
class HomeState extends Equatable {
  const HomeState({
    this.status = HomeStatus.loading,
    this.cities = const [],
    this.recentCities = const [],
    this.origin,
    this.destination,
    this.date,
    this.passengers = 1,
    this.nextTrip,
    this.today,
    this.errorMessage,
  });

  final HomeStatus status;

  /// Villes proposées dans les sélecteurs départ / arrivée.
  final List<City> cities;

  /// Dernières villes recherchées, proposées en raccourci sur l'écran de
  /// sélection de ville.
  final List<City> recentCities;

  final City? origin;
  final City? destination;
  final DateTime? date;
  final int passengers;

  /// `null` = aucun voyage à venir (voir la note sur [HomeStatus]).
  final UpcomingTrip? nextTrip;

  /// Date du jour, figée au chargement de l'écran : c'est la base des
  /// libellés relatifs (« Dans 7 jours » sur la carte du prochain
  /// voyage). Elle vient de l'horloge injectable du Bloc — un widget qui
  /// appellerait `DateTime.now()` de son côté afficherait un décompte
  /// calculé sur une autre horloge que les données, et deviendrait
  /// intestable. `null` tant que l'écran n'a pas fini de charger.
  final DateTime? today;

  /// Renseigné uniquement quand [status] vaut [HomeStatus.failure] — c'est
  /// le seul cas où l'écran le lit.
  final String? errorMessage;

  /// La recherche n'est lançable que si le trajet est complet et cohérent
  /// (on ne cherche pas un Cotonou → Cotonou).
  bool get canSearch =>
      origin != null && destination != null && origin != destination;

  /// Les critères prêts à être passés à l'écran de résultats, ou `null`
  /// tant que le formulaire est incomplet.
  SearchCriteria? get criteria {
    if (!canSearch) return null;
    return SearchCriteria(
      origin: origin!,
      destination: destination!,
      date: date ?? DateTime.now(),
      passengers: passengers,
    );
  }

  HomeState copyWith({
    HomeStatus? status,
    List<City>? cities,
    List<City>? recentCities,
    City? origin,
    City? destination,
    DateTime? date,
    int? passengers,
    UpcomingTrip? nextTrip,
    DateTime? today,
    String? errorMessage,
  }) {
    return HomeState(
      status: status ?? this.status,
      cities: cities ?? this.cities,
      recentCities: recentCities ?? this.recentCities,
      origin: origin ?? this.origin,
      destination: destination ?? this.destination,
      date: date ?? this.date,
      passengers: passengers ?? this.passengers,
      nextTrip: nextTrip ?? this.nextTrip,
      today: today ?? this.today,
      errorMessage: errorMessage ?? this.errorMessage,
    );
  }

  @override
  List<Object?> get props => [
    status,
    cities,
    recentCities,
    origin,
    destination,
    date,
    passengers,
    nextTrip,
    today,
    errorMessage,
  ];
}
