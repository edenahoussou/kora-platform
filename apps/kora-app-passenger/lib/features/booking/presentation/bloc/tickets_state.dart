part of 'tickets_bloc.dart';

enum TicketsStatus { loading, success, failure }

/// Les trois onglets de l'écran, dans l'ordre de la maquette.
enum TicketFilter {
  upcoming(TicketStatus.confirmed),
  past(TicketStatus.completed),
  cancelled(TicketStatus.cancelled);

  const TicketFilter(this.status);

  /// Statut de billet que cet onglet retient.
  final TicketStatus status;
}

class TicketsState extends Equatable {
  const TicketsState({
    this.status = TicketsStatus.loading,
    this.tickets = const [],
    this.filter = TicketFilter.upcoming,
    this.errorMessage = '',
    required this.today,
  });

  final TicketsStatus status;

  /// Tous les billets, statuts confondus : les compteurs des onglets ont
  /// besoin de la liste entière, pas seulement de l'onglet actif.
  final List<Ticket> tickets;

  final TicketFilter filter;
  final String errorMessage;

  /// Date du jour au moment du chargement, injectée par le bloc.
  ///
  /// L'écran s'en sert pour « Départ dans 7 jours » plutôt que d'appeler
  /// `DateTime.now()` lui-même : sans ça, l'écran et les données mockées
  /// pourraient se référer à deux instants différents.
  final DateTime today;

  /// Les billets de l'onglet actif, du départ le plus proche au plus
  /// lointain pour les voyages à venir, et du plus récent au plus ancien
  /// pour l'historique — on cherche rarement son trajet d'il y a six mois
  /// en premier.
  List<Ticket> get visibleTickets {
    final filtered = tickets
        .where((ticket) => ticket.status == filter.status)
        .toList();

    filtered.sort(
      (a, b) => filter == TicketFilter.upcoming
          ? a.departure.departsAt.compareTo(b.departure.departsAt)
          : b.departure.departsAt.compareTo(a.departure.departsAt),
    );

    return filtered;
  }

  int countFor(TicketFilter filter) =>
      tickets.where((ticket) => ticket.status == filter.status).length;

  TicketsState copyWith({
    TicketsStatus? status,
    List<Ticket>? tickets,
    TicketFilter? filter,
    String? errorMessage,
    DateTime? today,
  }) {
    return TicketsState(
      status: status ?? this.status,
      tickets: tickets ?? this.tickets,
      filter: filter ?? this.filter,
      errorMessage: errorMessage ?? this.errorMessage,
      today: today ?? this.today,
    );
  }

  @override
  List<Object?> get props => [status, tickets, filter, errorMessage, today];
}
