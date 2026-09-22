import 'package:equatable/equatable.dart';

/// État persisté du suivi en cours (table `tracking_session_state`,
/// MEZ-101) — restauré au démarrage de l'app ET à chaque `onStart` du
/// service premier plan, pour reprendre après un crash ou un redémarrage
/// à froid sans perdre le fil de la séquence ni republier un « démarrage »
/// pour une session déjà en cours.
class TrackingSessionSnapshot extends Equatable {
  const TrackingSessionSnapshot({
    required this.departureUuid,
    required this.terminalSerial,
    required this.isActive,
    required this.lastSequence,
    required this.startedAt,
  });

  final String departureUuid;
  final String terminalSerial;
  final bool isActive;
  final int lastSequence;
  final DateTime startedAt;

  @override
  List<Object?> get props => <Object?>[
        departureUuid,
        terminalSerial,
        isActive,
        lastSequence,
        startedAt,
      ];
}
