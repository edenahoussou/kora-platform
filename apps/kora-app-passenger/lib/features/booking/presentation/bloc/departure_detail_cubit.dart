import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/departure.dart';
import '../../domain/repositories/departure_repository.dart';

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
  DepartureDetailCubit(this._repository, {required Departure departure})
    : super(DepartureDetailState(departure: departure));

  final DepartureRepository _repository;

  Future<void> refresh() async {
    if (state.isRefreshing) return;

    emit(state.copyWith(isRefreshing: true, clearStale: true));

    final result = await _repository.byId(state.departure.id);

    emit(
      result.fold(
        (failure) => state.copyWith(isRefreshing: false, isStale: true),
        (departure) =>
            DepartureDetailState(departure: departure, isRefreshing: false),
      ),
    );
  }
}

class DepartureDetailState extends Equatable {
  const DepartureDetailState({
    required this.departure,
    this.isRefreshing = false,
    this.isStale = false,
  });

  /// Ce que l'écran affiche : le départ de la liste au départ, celui du
  /// serveur une fois rechargé.
  final Departure departure;

  final bool isRefreshing;

  /// Le rafraîchissement a échoué : ce qui est à l'écran vient de la
  /// recherche et peut avoir vieilli.
  final bool isStale;

  DepartureDetailState copyWith({
    Departure? departure,
    bool? isRefreshing,
    bool? isStale,
    bool clearStale = false,
  }) {
    return DepartureDetailState(
      departure: departure ?? this.departure,
      isRefreshing: isRefreshing ?? this.isRefreshing,
      isStale: clearStale ? false : (isStale ?? this.isStale),
    );
  }

  @override
  List<Object?> get props => [departure, isRefreshing, isStale];
}
