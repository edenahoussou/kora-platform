import 'dart:async';

import 'package:flutter_foreground_task/flutter_foreground_task.dart';

import '../../domain/entities/tracking_availability.dart';
import '../../domain/entities/tracking_runtime_status.dart';
import '../../domain/entities/tracking_session_snapshot.dart';
import '../../domain/entities/tracking_stop_reason.dart';
import '../../domain/repositories/tracking_repository.dart';
import '../../domain/services/location_permission_status.dart';
import '../../domain/services/location_provider.dart';
import '../datasources/tracking_local_datasource.dart';
import '../tracking_task_handler.dart';

/// Orchestration côté isolate principal — voir docstring
/// `TrackingRepository`. [_local] ici est l'instance PARTAGÉE de
/// `get_it` (contrairement à celle que `TrackingTaskHandler` construit
/// pour lui-même) : utilisée en lecture pour l'indicateur de file en
/// attente et en écriture uniquement pour amorcer une session
/// ([start]/[bootstrap]) avant que le service ne démarre.
class TrackingRepositoryImpl implements TrackingRepository {
  TrackingRepositoryImpl(this._local, this._locationProvider) {
    FlutterForegroundTask.addTaskDataCallback(_onTaskData);
  }

  final TrackingLocalDataSource _local;
  final LocationProvider _locationProvider;

  final StreamController<TrackingRuntimeStatus> _statusController =
      StreamController<TrackingRuntimeStatus>.broadcast();

  @override
  Stream<TrackingRuntimeStatus> get statusStream => _statusController.stream;

  void _onTaskData(Object data) {
    if (data is Map<Object?, Object?>) {
      _statusController.add(TrackingRuntimeStatus.fromData(data));
    }
  }

  @override
  Future<TrackingAvailability> checkAvailability() async {
    final LocationPermissionStatus permission =
        await _locationProvider.checkPermission();
    return _availabilityOf(permission);
  }

  @override
  Future<TrackingAvailability> requestAvailability() async {
    final LocationPermissionStatus permission =
        await _locationProvider.requestPermission();
    return _availabilityOf(permission);
  }

  Future<TrackingAvailability> _availabilityOf(
    LocationPermissionStatus permission,
  ) async {
    if (!permission.allowsBackgroundTracking) {
      return TrackingAvailability.permissionDenied;
    }

    final bool serviceEnabled =
        await _locationProvider.isLocationServiceEnabled();
    if (!serviceEnabled) return TrackingAvailability.gpsDisabled;

    final bool precise = await _locationProvider.hasPreciseAccuracy();
    if (!precise) return TrackingAvailability.insufficientAccuracy;

    return TrackingAvailability.ready;
  }

  @override
  Future<TrackingAvailability> start({
    required String departureUuid,
    required String terminalSerial,
  }) async {
    final TrackingAvailability availability = await checkAvailability();
    if (availability != TrackingAvailability.ready) return availability;

    final TrackingSessionSnapshot? existing = await _local.readSession();

    // Déjà actif pour CE départ : idempotent, ne redémarre rien (évite de
    // remettre la séquence à zéro si `start` est rappelé par erreur, ex.
    // double appui sur "Marquer le bus parti").
    if (existing != null &&
        existing.isActive &&
        existing.departureUuid == departureUuid &&
        await FlutterForegroundTask.isRunningService) {
      return TrackingAvailability.ready;
    }

    // Session active pour un AUTRE départ (ne devrait pas arriver en
    // usage normal — un convoyeur gère un départ à la fois — mais
    // défensif, même principe que la réassignation de terminal côté
    // backend, voir `OpsTrackingController::resolveSession`) : on l'arrête
    // proprement avant d'en ouvrir une nouvelle.
    if (existing != null &&
        existing.isActive &&
        await FlutterForegroundTask.isRunningService) {
      await stop(reason: TrackingStopReason.manualClose);
    }

    await _local.saveSession(TrackingSessionSnapshot(
      departureUuid: departureUuid,
      terminalSerial: terminalSerial,
      isActive: true,
      lastSequence: 0,
      startedAt: DateTime.now(),
    ));

    _initForegroundTask();

    await FlutterForegroundTask.startService(
      serviceTypes: const <ForegroundServiceTypes>[
        ForegroundServiceTypes.location
      ],
      notificationTitle: 'KORA OPS — suivi de position actif',
      notificationText: 'Démarrage du suivi…',
      callback: trackingStartCallback,
    );

    return TrackingAvailability.ready;
  }

  @override
  Future<void> stop({required TrackingStopReason reason}) async {
    // Le TaskHandler flush le dernier point connu (critère d'acceptation
    // « envoyer immédiatement... l'arrêt »), efface la session puis
    // arrête le service lui-même — voir `TrackingTaskHandler.
    // onReceiveData`. [reason] n'est pas transmise au serveur : le
    // backend connaît déjà le motif via la transition de statut du départ
    // elle-même (`progressDeparture`, indépendante du suivi), ce n'est ici
    // qu'un repère local.
    FlutterForegroundTask.sendDataToTask('stop');
    _statusController.add(const TrackingRuntimeStatus.initial());
  }

  @override
  Future<void> bootstrap() async {
    final TrackingSessionSnapshot? session = await _local.readSession();
    if (session == null || !session.isActive) return;

    final bool alreadyRunning = await FlutterForegroundTask.isRunningService;
    if (alreadyRunning) {
      return; // a survécu au redémarrage de l'app, rien à faire
    }

    // Le service a été tué (crash, redémarrage à froid, OS agressif) mais
    // la session est restée `active` en base : on la redémarre à
    // l'identique — `TrackingTaskHandler.onStart` relit la même session
    // et reprend la séquence là où elle en était (critère d'acceptation
    // « restaurer proprement... après crash »). Aucune vérification de
    // permission ici : elle a déjà été accordée pour démarrer la
    // première fois, un refus entre-temps sera simplement constaté par
    // `TrackingTaskHandler` (le flux de position ne produira rien).
    _initForegroundTask();
    await FlutterForegroundTask.startService(
      serviceTypes: const <ForegroundServiceTypes>[
        ForegroundServiceTypes.location
      ],
      notificationTitle: 'KORA OPS — suivi de position actif',
      notificationText: 'Reprise du suivi…',
      callback: trackingStartCallback,
    );
  }

  @override
  Future<bool> isActiveFor(String departureUuid) async {
    final TrackingSessionSnapshot? session = await _local.readSession();
    if (session == null || !session.isActive) return false;
    if (session.departureUuid != departureUuid) return false;
    return FlutterForegroundTask.isRunningService;
  }

  @override
  Future<int> totalPendingCount() => _local.totalPendingCount();

  /// Idempotent (voir doc `flutter_foreground_task`) — rappelée à chaque
  /// [start]/[bootstrap] plutôt qu'une seule fois au lancement de l'app :
  /// c'est `FlutterForegroundTask.init` qui configure le canal de
  /// notification, autant le faire juste avant `startService` pour rester
  /// à côté du code qui en dépend. Le type de service Android
  /// (`ForegroundServiceTypes.location`, exigé Android 14+) se déclare à
  /// `startService`, pas ici (`AndroidNotificationOptions` n'a plus de
  /// paramètre `foregroundServiceType` dans la version du package
  /// utilisée — voir les deux appels `startService` ci-dessus).
  void _initForegroundTask() {
    FlutterForegroundTask.init(
      androidNotificationOptions: AndroidNotificationOptions(
        channelId: 'kora_ops_tracking',
        channelName: 'Suivi de position',
        channelDescription:
            'Notification affichée pendant le suivi de position du '
            'convoyeur — requise par Android tant que le suivi est actif.',
        channelImportance: NotificationChannelImportance.LOW,
        priority: NotificationPriority.LOW,
        onlyAlertOnce: true,
      ),
      iosNotificationOptions: const IOSNotificationOptions(
        showNotification: false,
        playSound: false,
      ),
      foregroundTaskOptions: ForegroundTaskOptions(
        eventAction: ForegroundTaskEventAction.repeat(30000),
        autoRunOnBoot: true,
        autoRunOnMyPackageReplaced: true,
        allowWakeLock: true,
        allowWifiLock: true,
      ),
    );
  }
}
