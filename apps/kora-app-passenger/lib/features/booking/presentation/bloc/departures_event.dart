part of 'departures_bloc.dart';

/// Couche PRESENTATION : ce qui peut arriver sur l'écran des départs.
sealed class DeparturesEvent extends Equatable {
  const DeparturesEvent();

  @override
  List<Object?> get props => [];
}

/// Lancement (ou relance) de la recherche.
///
/// Porte les paramètres **bruts de l'URL**, pas des objets métier : c'est
/// le Bloc qui les résout en [SearchCriteria], comme il devra le faire au
/// Sprint 2 pour un lien profond ouvert directement sur cet écran.
class DeparturesRequested extends DeparturesEvent {
  const DeparturesRequested({
    this.originId,
    this.destinationId,
    this.date,
    this.passengers,
  });

  final String? originId;
  final String? destinationId;

  /// Jour de départ au format `aaaa-mm-jj`.
  final String? date;

  final String? passengers;

  @override
  List<Object?> get props => [originId, destinationId, date, passengers];
}

/// Bouton « Réessayer » de l'écran d'erreur : rejoue la dernière
/// recherche, sans que l'écran ait à en reconstruire les paramètres.
class DeparturesRetried extends DeparturesEvent {
  const DeparturesRetried();
}

/// Changement de tri depuis les puces « Heure » / « Prix ».
class DeparturesSortChanged extends DeparturesEvent {
  const DeparturesSortChanged(this.sort);

  final DepartureSort sort;

  @override
  List<Object?> get props => [sort];
}
