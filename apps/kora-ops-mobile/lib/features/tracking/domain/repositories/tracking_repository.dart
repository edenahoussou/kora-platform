import '../entities/tracking_availability.dart';
import '../entities/tracking_runtime_status.dart';
import '../entities/tracking_stop_reason.dart';

/// Orchestration du suivi côté isolate principal (UI) — MEZ-101. Ne
/// mesure jamais de position elle-même : [start] démarre le service
/// premier plan (`TrackingTaskHandler`, isolate séparé, voir sa
/// docstring) qui s'en charge ; ce repository lit seulement l'état
/// persisté et relaie le statut envoyé par le TaskHandler.
abstract class TrackingRepository {
  /// Vérifie permission, GPS activé et précision — SANS déclencher de
  /// boîte de dialogue système (voir [requestAvailability] pour ça).
  /// Utilisée pour l'affichage passif de l'état (voir critère
  /// d'acceptation « le convoyeur sait pourquoi le suivi est
  /// indisponible »).
  Future<TrackingAvailability> checkAvailability();

  /// Demande activement permission (et GPS si besoin, via les réglages
  /// système) — à appeler uniquement sur une action explicite du
  /// convoyeur (jamais en tâche de fond, ce sont des boîtes de dialogue
  /// système).
  Future<TrackingAvailability> requestAvailability();

  /// Démarre le service premier plan pour ce départ — no-op si un suivi
  /// est déjà actif pour LE MÊME départ (voir `TrackingRepositoryImpl.
  /// start`). Échoue avec le [TrackingAvailability] bloquant si les
  /// conditions ne sont pas réunies : appeler [requestAvailability]
  /// avant si nécessaire.
  Future<TrackingAvailability> start({
    required String departureUuid,
    required String terminalSerial,
  });

  /// Arrête le suivi — flush immédiat du dernier point connu côté
  /// TaskHandler avant l'arrêt effectif du service (voir critère
  /// d'acceptation « envoyer immédiatement... l'arrêt »).
  Future<void> stop({required TrackingStopReason reason});

  /// À appeler une fois au démarrage de l'app (voir `SplashPage`) :
  /// reprend un suivi resté actif après un crash ou un redémarrage à
  /// froid (le service Android peut avoir survécu tout seul —
  /// `autoRunOnBoot`/`stopWithTask=false` — ou avoir été tué avec le
  /// process, auquel cas on le redémarre depuis l'état persisté).
  Future<void> bootstrap();

  /// `true` si le service premier plan tourne actuellement pour ce
  /// départ — après [bootstrap], reflète un suivi qui a survécu à un
  /// redémarrage de l'app.
  Future<bool> isActiveFor(String departureUuid);

  /// Diffusion de l'état une fois actif (file en attente, en ligne,
  /// synchro en cours) — voir `TrackingRuntimeStatus`.
  Stream<TrackingRuntimeStatus> get statusStream;

  Future<int> totalPendingCount();
}
