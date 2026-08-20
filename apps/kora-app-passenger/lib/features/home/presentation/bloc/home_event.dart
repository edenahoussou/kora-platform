part of 'home_bloc.dart';

/// Couche PRESENTATION : tout ce que l'utilisateur (ou l'app) peut
/// déclencher sur l'écran d'accueil.
sealed class HomeEvent extends Equatable {
  const HomeEvent();

  @override
  List<Object?> get props => [];
}

/// Chargement initial de l'écran (villes + prochain voyage). Sert aussi de
/// « Réessayer » depuis l'état d'erreur : c'est exactement la même action.
class HomeStarted extends HomeEvent {
  const HomeStarted();
}

class HomeOriginSelected extends HomeEvent {
  const HomeOriginSelected(this.city);

  final City city;

  @override
  List<Object?> get props => [city];
}

class HomeDestinationSelected extends HomeEvent {
  const HomeDestinationSelected(this.city);

  final City city;

  @override
  List<Object?> get props => [city];
}

/// Bouton ⇅ : intervertit départ et arrivée.
class HomeRouteSwapped extends HomeEvent {
  const HomeRouteSwapped();
}

class HomeDateSelected extends HomeEvent {
  const HomeDateSelected(this.date);

  final DateTime date;

  @override
  List<Object?> get props => [date];
}

class HomePassengersSelected extends HomeEvent {
  const HomePassengersSelected(this.passengers);

  final int passengers;

  @override
  List<Object?> get props => [passengers];
}

/// Bascule entre aller simple et aller-retour.
class HomeTripTypeChanged extends HomeEvent {
  const HomeTripTypeChanged(this.tripType);

  final TripType tripType;

  @override
  List<Object?> get props => [tripType];
}

/// Date du retour choisie sur l'écran de calendrier.
class HomeReturnDateSelected extends HomeEvent {
  const HomeReturnDateSelected(this.date);

  final DateTime date;

  @override
  List<Object?> get props => [date];
}
