import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/itinerary.dart';
import '../../domain/entities/line_stop.dart';
import '../../domain/repositories/booking_repository.dart';

/// Le détail d'un départ, rafraîchi en arrière-plan.
///
/// **Il part de ce que la liste a déjà affiché**, puis recharge par
/// `GET bus/departures/{uuid}`. L'écran n'a donc jamais de squelette :
/// remplacer par un chargement des données qu'on vient de montrer ferait
/// clignoter l'écran pour rien.
///
/// Ce qu'on va chercher n'est pas plus de données, ce sont les mêmes,
/// fraîches. Entre la recherche et l'ouverture du détail, les places
/// restantes et le tarif ont pu bouger — et c'est juste avant de choisir
/// un siège qu'une donnée périmée coûte le plus cher.
///
/// Un échec ne casse rien : on garde le départ de la liste et on le dit,
/// discrètement. Vider l'écran parce qu'un rafraîchissement a échoué
/// priverait le voyageur d'informations qu'il avait déjà sous les yeux.
class DepartureDetailCubit extends Cubit<DepartureDetailState> {
  DepartureDetailCubit({
    required Itinerary itinerary,
    required BookingRepository repository,
  }) : _repository = repository, // ignore: prefer_initializing_formals
       super(DepartureDetailState(itinerary: itinerary));

  final BookingRepository _repository;

  /// Charge les arrêts de chaque ligne du voyage —
  /// `GET bus/lines/{uuid}`.
  ///
  /// Ils ne viennent pas avec l'itinéraire : `bus/itineraries` décrit le
  /// trajet demandé, pas la ligne entière. Une correspondance emprunte
  /// deux lignes, donc deux appels — chacun pour son tronçon, parce que
  /// les étapes de l'un n'apprennent rien sur l'autre.
  ///
  /// Un échec est silencieux, comme le reste de cet écran : la section
  /// « Les étapes » se tait pour cette ligne-là, et tout ce qui est déjà
  /// affiché reste utilisable.
  Future<void> loadStops() async {
    for (final leg in state.itinerary.legs) {
      final lineId = leg.line.id;
      if (lineId.isEmpty || state.stopsByLine.containsKey(lineId)) continue;

      final result = await _repository.getLineStops(lineId);
      if (isClosed) return;

      result.fold((_) {}, (stops) {
        emit(
          state.copyWith(stopsByLine: {...state.stopsByLine, lineId: stops}),
        );
      });
    }
  }

  /// Rafraîchit le départ affiché.
  ///
  /// **Ne fait plus rien depuis MEZ-63**, qui a retiré
  /// `GET bus/departures/{uuid}` : il n'existe plus de route pour relire un
  /// départ isolé. Continuer à l'appeler marquerait l'écran « périmé » en
  /// permanence, ce qui est pire que de ne pas rafraîchir.
  ///
  /// L'absence coûte peu : le détail s'ouvre quelques secondes après la
  /// recherche, et c'est le plan des sièges — servi par une route bien
  /// vivante — qui fait autorité sur les places restantes.
  ///
  /// À rebrancher le jour où le back exposera de nouveau un départ seul,
  /// ou un itinéraire par identifiant.
  Future<void> refresh() async {}
}

class DepartureDetailState extends Equatable {
  const DepartureDetailState({
    required this.itinerary,
    this.stopsByLine = const {},
    this.isRefreshing = false,
    this.isStale = false,
  });

  /// Ce que l'écran affiche : le voyage choisi dans la liste.
  final Itinerary itinerary;

  /// Les arrêts de chaque ligne empruntée, par identifiant de ligne.
  /// Absente tant qu'elle n'a pas été chargée, vide quand la ligne n'en
  /// publie aucun.
  final Map<String, List<LineStop>> stopsByLine;

  /// Les arrêts de la ligne d'un tronçon, dans l'ordre.
  List<LineStop> stopsOf(String lineId) => stopsByLine[lineId] ?? const [];

  final bool isRefreshing;

  /// Le rafraîchissement a échoué : ce qui est à l'écran vient de la
  /// recherche et peut avoir vieilli.
  final bool isStale;

  DepartureDetailState copyWith({
    Itinerary? itinerary,
    Map<String, List<LineStop>>? stopsByLine,
    bool? isRefreshing,
    bool? isStale,
    bool clearStale = false,
  }) {
    return DepartureDetailState(
      itinerary: itinerary ?? this.itinerary,
      stopsByLine: stopsByLine ?? this.stopsByLine,
      isRefreshing: isRefreshing ?? this.isRefreshing,
      isStale: clearStale ? false : (isStale ?? this.isStale),
    );
  }

  @override
  List<Object?> get props => [itinerary, stopsByLine, isRefreshing, isStale];
}
