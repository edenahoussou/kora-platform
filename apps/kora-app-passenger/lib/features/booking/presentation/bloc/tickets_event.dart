part of 'tickets_bloc.dart';

sealed class TicketsEvent extends Equatable {
  const TicketsEvent();

  @override
  List<Object?> get props => [];
}

/// Émis à l'ouverture de l'écran, et par le bouton « Réessayer ».
class TicketsStarted extends TicketsEvent {
  const TicketsStarted();
}

/// Émis quand l'utilisateur change d'onglet.
class TicketsFilterChanged extends TicketsEvent {
  const TicketsFilterChanged(this.filter);

  final TicketFilter filter;

  @override
  List<Object?> get props => [filter];
}
