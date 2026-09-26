import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../app/config/app_config.dart';
import '../../../core/database/app_database.dart';
import '../../auth/data/datasources/auth_local_datasource.dart';
import '../domain/entities/tracking_position.dart';
import '../domain/entities/tracking_runtime_status.dart';
import '../domain/entities/tracking_session_snapshot.dart';
import '../domain/services/location_fix.dart';
import '../domain/services/location_provider.dart';
import 'datasources/tracking_local_datasource.dart';
import 'datasources/tracking_remote_datasource.dart';
import 'services/geolocator_location_provider.dart';
import 'services/mock_location_provider.dart';
import 'tracking_sync_engine.dart';

/// Point d'entrée du service premier plan — DOIT rester une fonction
/// top-level (contrainte `flutter_foreground_task`, l'annotation
/// `@pragma('vm:entry-point')` ci-dessous la rend résolvable par
/// l'isolate du service). Passée directement à
/// `FlutterForegroundTask.startService(callback: trackingStartCallback)`
/// — voir `TrackingRepositoryImpl`. Tourne dans un isolate séparé de
/// l'UI : aucun état de l'isolate principal (get_it, cubits...) n'est
/// accessible ici, voir docstring `TrackingTaskHandler`.
@pragma('vm:entry-point')
void trackingStartCallback() {
  FlutterForegroundTask.setTaskHandler(TrackingTaskHandler());
}

/// Adaptateur entre le service premier plan Android (`flutter_foreground_
/// task`) et [TrackingSyncEngine] (ticket MEZ-101) : mesure la position en
/// continu (flux GPS, changements d'état du service de localisation,
/// connectivité) et délègue toute décision d'enfilage/d'envoi au moteur.
/// Volontairement fin — toute la logique métier testable vit dans
/// [TrackingSyncEngine], voir sa docstring.
///
/// Isolat séparé de l'UI : ne peut RIEN réutiliser de `get_it` — construit
/// ses propres instances de `AppDatabase` (sqflite gère nativement
/// plusieurs connexions concurrentes vers le même fichier, voir
/// `AppDatabase`), de `Dio` (mêmes en-têtes Authorization/X-Terminal-
/// Serial que `opsDioClient`, reconstruits ici à l'identique) et du
/// `LocationProvider` réel/Mock (même bascule `AppConfig.useMockHardware`
/// que côté UI — `AppConfig.init()` doit être rappelé ici, l'instance
/// statique de l'isolate principal ne traverse pas).
///
/// Aucun WebSocket : uniquement des lots HTTP par [TrackingSyncEngine],
/// conformément au contrat MEZ-100 (critère d'acceptation « ne pas
/// démarrer de WebSocket mobile persistant »).
class TrackingTaskHandler extends TaskHandler {
  static const Duration _flushInterval = Duration(seconds: 15);
  static const int _positionIntervalSeconds = 12;
  static const double _minDistanceMeters = 15;

  late TrackingLocalDataSource _local;
  late TrackingSyncEngine _engine;
  late LocationProvider _locationProvider;

  StreamSubscription<LocationFix>? _positionSubscription;
  StreamSubscription<bool>? _serviceEnabledSubscription;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;
  Timer? _flushTimer;

  String? _departureUuid;

  /// Positionné par [onReceiveData] avant l'arrêt volontaire — distingue
  /// « arrêt demandé » (session effacée, voir [onReceiveData]) d'un
  /// service tué par l'OS (session laissée `active`, reprise au prochain
  /// lancement de l'app — voir `TrackingRepositoryImpl.bootstrap`).
  bool _stopRequested = false;

  @override
  Future<void> onStart(DateTime timestamp, TaskStarter starter) async {
    // Isolate neuf : aucune configuration statique ne survit depuis
    // l'isolate principal (voir docstring de la classe).
    AppConfig.init();

    final AppDatabase database = AppDatabase();
    _local = TrackingLocalDataSourceImpl(database);

    final TrackingSessionSnapshot? session = await _local.readSession();
    if (session == null || !session.isActive) {
      // Service démarré sans session valide persistée (ne devrait pas
      // arriver via TrackingRepositoryImpl.start, qui écrit toujours la
      // session AVANT de démarrer le service) — rien à faire.
      await FlutterForegroundTask.stopService();
      return;
    }

    _departureUuid = session.departureUuid;

    final Dio dio = await _buildDio(session.terminalSerial);
    _engine = TrackingSyncEngine(_local, TrackingRemoteDataSourceImpl(dio));
    _locationProvider = AppConfig.instance.useMockHardware
        ? MockLocationProvider()
        : GeolocatorLocationProvider();

    _positionSubscription = _locationProvider
        .watchPosition(
          intervalSeconds: _positionIntervalSeconds,
          minDistanceMeters: _minDistanceMeters,
        )
        .listen(_onFix, onError: (Object _) {});

    _serviceEnabledSubscription = _locationProvider
        .watchServiceEnabled()
        .listen(_onServiceEnabledChanged);

    _connectivitySubscription =
        Connectivity().onConnectivityChanged.listen(_onConnectivityChanged);

    _flushTimer =
        Timer.periodic(_flushInterval, (Timer _) => _flushAndReport());

    await _reportStatus();
  }

  /// Même en-têtes que `opsDioClient` (voir `injection.dart`) : jeton
  /// courant (relu à chaque requête — un renouvellement pendant le
  /// service reste transparent) + numéro de série du terminal. Pas de
  /// `SessionExpiryInterceptor` ici : un 401 en tâche de fond n'a personne
  /// à qui le signaler ; l'app le détectera normalement à sa prochaine
  /// ouverture (voir `_AppLifecycleListener`).
  Future<Dio> _buildDio(String terminalSerial) async {
    const FlutterSecureStorage storage = FlutterSecureStorage();
    final AuthLocalDataSource authLocal = AuthLocalDataSourceImpl(storage);

    final Dio dio = Dio(BaseOptions(
      baseUrl: AppConfig.instance.apiBaseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 20),
      headers: <String, String>{'Content-Type': 'application/json'},
    ));

    dio.interceptors.add(InterceptorsWrapper(
      onRequest:
          (RequestOptions options, RequestInterceptorHandler handler) async {
        final session = await authLocal.readSession();
        if (session?.token != null) {
          options.headers['Authorization'] = 'Bearer ${session!.token}';
        }
        options.headers['X-Terminal-Serial'] = terminalSerial;
        handler.next(options);
      },
    ));

    return dio;
  }

  Future<void> _onFix(LocationFix fix) async {
    final String? departureUuid = _departureUuid;
    if (departureUuid == null) return;

    final TrackingPosition position =
        await _engine.enqueueFix(departureUuid, fix);

    // Démarrage (critère d'acceptation) : le tout premier point de la
    // session est envoyé immédiatement plutôt que d'attendre le prochain
    // cycle du minuteur, pour que OPS Web voie le bus en circulation dans
    // la seconde plutôt qu'à la prochaine échéance de 15 s.
    if (position.sequence == 1) {
      unawaited(_flushAndReport());
    } else {
      await _reportStatus();
    }
  }

  /// Perte de signal (critère d'acceptation) : dès que l'OS signale le
  /// GPS désactivé, un heartbeat est enfilé ET envoyé immédiatement — le
  /// retour à `true` n'a rien à envoyer de spécial, le prochain fix réel
  /// suffit à signaler la reprise.
  Future<void> _onServiceEnabledChanged(bool enabled) async {
    if (enabled) return;
    final String? departureUuid = _departureUuid;
    if (departureUuid == null) return;

    await _engine.enqueueGpsUnavailable(departureUuid);
    unawaited(_flushAndReport());
  }

  /// Reconnexion (critère d'acceptation) : envoi immédiat de la file
  /// accumulée hors ligne, sans attendre le prochain cycle du minuteur.
  Future<void> _onConnectivityChanged(List<ConnectivityResult> results) async {
    final bool wasOnline = _engine.isOnline;
    final bool isOnline = !results.contains(ConnectivityResult.none);

    if (isOnline) {
      _engine.markOnline();
    } else {
      _engine.markOffline();
    }

    if (isOnline && !wasOnline) {
      unawaited(_flushAndReport());
    } else {
      await _reportStatus();
    }
  }

  /// Retente TOUJOURS l'envoi, quel que soit l'état `isOnline` mémorisé
  /// par [_engine] — corrigé le 24/09/2026 (positions restées bloquées
  /// "en attente" indéfiniment) : `_engine` ne repasse `isOnline` à vrai
  /// que sur un changement d'état de connectivité détecté par l'OS
  /// (`_onConnectivityChanged`) ; si l'échec initial vient d'un incident
  /// ponctuel (délai serveur, DNS...) sans que la connectivité elle-même
  /// n'ait jamais varié aux yeux de l'OS, cet événement ne se déclenche
  /// jamais — la file restait alors bloquée hors ligne pour de bon,
  /// malgré un réseau réellement fonctionnel. Un `markOnline()` avant
  /// chaque tentative (au minimum toutes les 15 s, voir [_flushTimer])
  /// garantit qu'on retente régulièrement quoi qu'il arrive : `flush()`
  /// remettra lui-même `isOnline` à faux si l'envoi échoue à nouveau,
  /// sans jamais rester bloqué plus d'un cycle.
  Future<void> _flushAndReport() async {
    final String? departureUuid = _departureUuid;
    if (departureUuid == null) return;
    _engine.markOnline();
    await _reportStatus(); // reflète isFlushing=true immédiatement
    await _engine.flush(departureUuid);
    await _local.pruneSynced(olderThan: const Duration(days: 2));
    await _reportStatus();
  }

  Future<void> _reportStatus() async {
    final String? departureUuid = _departureUuid;
    final int pendingCount =
        departureUuid == null ? 0 : await _engine.pendingCount(departureUuid);

    final TrackingRuntimeStatus status = TrackingRuntimeStatus(
      isOnline: _engine.isOnline,
      isSyncing: _engine.isFlushing,
      pendingCount: pendingCount,
      lastPositionAt: DateTime.now(),
      lastError: _engine.lastError,
    );

    FlutterForegroundTask.sendDataToMain(status.toData());
    await FlutterForegroundTask.updateService(
      notificationTitle: 'KORA OPS — suivi de position actif',
      notificationText: !status.isOnline
          ? 'Hors ligne — $pendingCount point(s) en file'
          : status.isSyncing
              ? 'Synchronisation…'
              : pendingCount > 0
                  ? '$pendingCount point(s) en attente'
                  : 'Position transmise',
    );
  }

  /// Aucun travail périodique ici : le rythme de mesure est piloté par le
  /// flux GPS lui-même ([_positionIntervalSeconds]) et l'envoi par
  /// [_flushTimer] — `ForegroundTaskOptions.eventAction` (voir
  /// `TrackingRepositoryImpl._initForegroundTask`) ne sert qu'à
  /// l'exigence de la plateforme (fréquence minimale pour la notification
  /// vivante), pas de logique métier.
  @override
  void onRepeatEvent(DateTime timestamp) {}

  /// `'stop'` : arrêt volontaire demandé par `TrackingRepositoryImpl.stop`
  /// (convoyeur qui marque l'arrivée/l'annulation) — flush immédiat
  /// (critère d'acceptation « envoyer immédiatement... l'arrêt ») puis la
  /// session est effacée AVANT l'arrêt du service, pour que [onDestroy]
  /// sache ne rien restaurer au prochain lancement.
  @override
  void onReceiveData(Object data) {
    if (data != 'stop') return;

    unawaited(() async {
      _stopRequested = true;
      final String? departureUuid = _departureUuid;
      if (departureUuid != null) await _engine.flush(departureUuid);
      await _local.clearSession();
      await FlutterForegroundTask.stopService();
    }());
  }

  /// Peut être appelé par un arrêt volontaire déjà traité ci-dessus, OU
  /// par l'OS qui tue le service (mémoire, optimisation batterie
  /// agressive...) — dans ce second cas, [_stopRequested] est resté
  /// `false` : la session reste `active` en base, `TrackingRepositoryImpl.
  /// bootstrap()` la redémarrera au prochain lancement de l'app, sans
  /// perte des points déjà en file (critère d'acceptation « restaurer
  /// proprement... après crash »).
  @override
  Future<void> onDestroy(DateTime timestamp, bool isTimeout) async {
    await _positionSubscription?.cancel();
    await _serviceEnabledSubscription?.cancel();
    await _connectivitySubscription?.cancel();
    _flushTimer?.cancel();

    final String? departureUuid = _departureUuid;
    if (!_stopRequested && departureUuid != null) {
      // Tentative best-effort — le process peut être tué avant la fin,
      // sans conséquence : la file reste intacte en base pour la reprise.
      await _engine.flush(departureUuid);
    }
  }
}
