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
    this.returnDate,
  });

  final String? originId;
  final String? destinationId;

  /// Jour de départ au format `aaaa-mm-jj`.
  final String? date;

  final String? passengers;

  /// Jour du retour au même format, absent sur un aller simple.
  final String? returnDate;

  @override
  List<Object?> get props => [
    originId,
    destinationId,
    date,
    passengers,
    returnDate,
  ];
}

/// Bouton « Réessayer » de l'écran d'erreur : rejoue la dernière
/// recherche, sans que l'écran ait à en reconstruire les paramètres.
class DeparturesRetried extends DeparturesEvent {
  const DeparturesRetried();
}

/// Tir sur une puce de tri (« Heure », « Prix », « Durée »).
///
/// Un seul événement pour deux gestes, parce que c'est un seul geste pour
/// l'utilisateur : taper une puce inactive change de critère, retaper la
/// puce active inverse le sens. C'est le Bloc qui tranche — l'écran n'a
/// pas à savoir quel tri est déjà en place pour émettre le bon événement.
class DeparturesSortChanged extends DeparturesEvent {
  const DeparturesSortChanged(this.field);

  final DepartureSortField field;

  @override
  List<Object?> get props => [field];
}

/// Tir sur une puce de créneau (« Matin », « Après-midi », « Soir ») :
/// ajoute le créneau aux filtres, ou l'en retire s'il y était.
class DeparturesTimeSlotToggled extends DeparturesEvent {
  const DeparturesTimeSlotToggled(this.slot);

  final DepartureTimeSlot slot;

  @override
  List<Object?> get props => [slot];
}

/// Retour à la liste complète, depuis l'écran « aucun départ ne
/// correspond ».
class DeparturesFiltersCleared extends DeparturesEvent {
  const DeparturesFiltersCleared();
}

/// Tiré-pour-actualiser sur la liste des résultats.
///
/// Distinct de [DeparturesRetried], qui repart d'un écran d'erreur : ici
/// des résultats sont déjà affichés et doivent le rester pendant l'appel.
/// Les places disponibles bougent en permanence, c'est le geste qui remet
/// la liste à jour sans refaire la recherche depuis le formulaire.
class DeparturesRefreshed extends DeparturesEvent {
  const DeparturesRefreshed();
}
