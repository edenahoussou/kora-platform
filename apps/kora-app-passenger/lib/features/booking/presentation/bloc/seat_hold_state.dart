part of 'seat_hold_bloc.dart';

/// Où en est le blocage.
///
/// [idle] n'est pas un état d'attente mais l'état **normal** de la
/// première visite : rien n'est retenu, donc pas de carte de décompte —
/// c'est le choix des sièges qui n'a pas encore été confirmé.
enum SeatHoldStatus { idle, holding, held, expired, failure }

class SeatHoldState extends Equatable {
  const SeatHoldState({
    this.status = SeatHoldStatus.idle,
    this.hold,
    this.remaining = Duration.zero,
    this.failure,
    this.departure,
    this.passengers = 1,
  });

  final SeatHoldStatus status;
  final SeatHold? hold;

  /// Le départ concerné, gardé au-delà de l'expiration : l'écran de fin de
  /// délai en a besoin pour renvoyer au bon plan de sièges, alors que le
  /// blocage, lui, n'existe plus.
  final Departure? departure;

  final int passengers;

  /// Recalculé à chaque seconde depuis `hold.expiresAt`, jamais décrémenté
  /// pas à pas : un minuteur qui compte ses propres battements dérive dès
  /// que l'app passe en arrière-plan, alors qu'une soustraction de dates
  /// rattrape le temps perdu.
  final Duration remaining;

  final Failure? failure;

  /// Des sièges sont retenus : le décompte court, sur tous les écrans du
  /// tunnel.
  bool get isHeld => status == SeatHoldStatus.held && hold != null;

  /// `06:42`, tel que l'affiche la maquette.
  String get formatted {
    final minutes = remaining.inMinutes.toString().padLeft(2, '0');
    final seconds = (remaining.inSeconds % 60).toString().padLeft(2, '0');
    return '$minutes:$seconds';
  }

  SeatHoldState copyWith({
    SeatHoldStatus? status,
    SeatHold? hold,
    Duration? remaining,
    Failure? errorMessage,
    Departure? departure,
    int? passengers,
    bool clearError = false,
  }) {
    return SeatHoldState(
      status: status ?? this.status,
      hold: hold ?? this.hold,
      remaining: remaining ?? this.remaining,
      failure: clearError ? null : (errorMessage ?? failure),
      departure: departure ?? this.departure,
      passengers: passengers ?? this.passengers,
    );
  }

  @override
  List<Object?> get props => [
    status,
    hold,
    remaining,
    failure,
    departure,
    passengers,
  ];
}
