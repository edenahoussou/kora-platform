import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/departure.dart';
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
    required Departure departure,
    required BookingRepository repository,
  }) : _repository = repository, // ignore: prefer_initializing_formals
       super(DepartureDetailState(departure: departure));

  final BookingRepository _repository;

  /// Charge les arrêts de la ligne — `GET bus/lines/{uuid}`.
  ///
  /// Ils ne viennent pas avec le départ : `bus/itineraries` décrit le
  /// trajet demandé, pas la ligne entière. Un échec est silencieux, comme
  /// le reste de cet écran : la section « Les étapes » se tait, et tout ce
  /// qui est déjà affiché reste utilisable.
  Future<void> loadStops() async {
    final lineId = state.departure.line.id;
    if (lineId.isEmpty) return;

    final result = await _repository.getLineStops(lineId);

    result.fold((_) {}, (stops) {
      if (isClosed) return;
      emit(state.copyWith(stops: stops));
    });
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
    required this.departure,
    this.stops = const [],
    this.isRefreshing = false,
    this.isStale = false,
  });

  /// Ce que l'écran affiche : le départ de la liste au départ, celui du
  /// serveur une fois rechargé.
  final Departure departure;

  /// Les arrêts de la ligne, dans l'ordre. Vide tant qu'ils n'ont pas
  /// été chargés, et vide pour de bon si la ligne n'en publie aucun.
  final List<LineStop> stops;

  final bool isRefreshing;

  /// Le rafraîchissement a échoué : ce qui est à l'écran vient de la
  /// recherche et peut avoir vieilli.
  final bool isStale;

  DepartureDetailState copyWith({
    Departure? departure,
    List<LineStop>? stops,
    bool? isRefreshing,
    bool? isStale,
    bool clearStale = false,
  }) {
    return DepartureDetailState(
      departure: departure ?? this.departure,
      stops: stops ?? this.stops,
      isRefreshing: isRefreshing ?? this.isRefreshing,
      isStale: clearStale ? false : (isStale ?? this.isStale),
    );
  }

  @override
  List<Object?> get props => [departure, stops, isRefreshing, isStale];
}
