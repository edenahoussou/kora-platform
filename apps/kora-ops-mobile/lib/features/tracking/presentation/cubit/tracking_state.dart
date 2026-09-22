part of 'tracking_cubit.dart';

class TrackingState extends Equatable {
  const TrackingState({
    this.isActive = false,
    this.availability = TrackingAvailability.ready,
    this.isOnline = true,
    this.isSyncing = false,
    this.pendingCount = 0,
    this.lastPositionAt,
  });

  /// Le service premier plan tourne actuellement.
  final bool isActive;

  /// Pourquoi le suivi n'est pas actif — non significatif quand
  /// [isActive] est vrai (voir docstring `TrackingAvailability`).
  final TrackingAvailability availability;

  /// Réseau disponible pour l'envoi des lots — le suivi continue de
  /// mesurer et mettre en file localement même hors ligne (voir
  /// `TrackingTaskHandler`), ce n'est donc jamais un état bloquant.
  final bool isOnline;

  final bool isSyncing;

  /// Nombre de positions en file locale, en attente d'envoi.
  final int pendingCount;

  final DateTime? lastPositionAt;

  TrackingState copyWith({
    bool? isActive,
    TrackingAvailability? availability,
    bool? isOnline,
    bool? isSyncing,
    int? pendingCount,
    DateTime? lastPositionAt,
  }) =>
      TrackingState(
        isActive: isActive ?? this.isActive,
        availability: availability ?? this.availability,
        isOnline: isOnline ?? this.isOnline,
        isSyncing: isSyncing ?? this.isSyncing,
        pendingCount: pendingCount ?? this.pendingCount,
        lastPositionAt: lastPositionAt ?? this.lastPositionAt,
      );

  @override
  List<Object?> get props => <Object?>[
        isActive,
        availability,
        isOnline,
        isSyncing,
        pendingCount,
        lastPositionAt,
      ];
}
