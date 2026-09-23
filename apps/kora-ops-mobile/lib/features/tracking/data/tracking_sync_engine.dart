import 'dart:math' as math;

import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

import '../domain/entities/tracking_position.dart';
import '../domain/entities/tracking_position_source.dart';
import '../domain/entities/tracking_position_status.dart';
import '../domain/services/location_fix.dart';
import 'datasources/tracking_local_datasource.dart';
import 'datasources/tracking_remote_datasource.dart';

/// Cœur TESTABLE du suivi : enfilage local et envoi par lots — extrait de
/// `TrackingTaskHandler` (MEZ-101) précisément pour ne dépendre que
/// d'interfaces mockables (`TrackingLocalDataSource`/
/// `TrackingRemoteDataSource`), jamais de `FlutterForegroundTask` ni de la
/// construction concrète de `Dio`/`AppDatabase` — voir les tests dans
/// `test/features/tracking/data/tracking_sync_engine_test.dart`, qui
/// couvrent les critères d'acceptation (idempotence, aucune perte sur
/// coupure réseau, ordre des séquences).
///
/// `TrackingTaskHandler` reste responsable de tout ce que cette classe ne
/// peut pas raisonnablement tester en isolation : le flux GPS réel, le
/// service premier plan Android, les timers/abonnements de l'isolate.
class TrackingSyncEngine {
  TrackingSyncEngine(this._local, this._remote, {Uuid? uuid})
      : _uuid = uuid ?? const Uuid();

  final TrackingLocalDataSource _local;
  final TrackingRemoteDataSource _remote;
  final Uuid _uuid;

  /// Nombre de positions envoyées par requête HTTP — le serveur plafonne
  /// à 200 (`config.tracking.max_positions_per_batch`), 50 ici pour
  /// garder chaque requête rapide même sur un réseau 2G/3G dégradé.
  static const int batchChunkSize = 50;

  bool _online = true;
  bool _flushing = false;

  bool get isOnline => _online;
  bool get isFlushing => _flushing;

  /// Enfile une mesure GPS réelle et retourne la position créée (sa
  /// séquence en particulier, pour que l'appelant sache si c'est la
  /// première de la session — voir `TrackingTaskHandler._onFix`).
  Future<TrackingPosition> enqueueFix(
      String departureUuid, LocationFix fix) async {
    final int sequence = await _local.nextSequence();
    final TrackingPosition position = TrackingPosition(
      positionUuid: _uuid.v4(),
      departureUuid: departureUuid,
      sequence: sequence,
      status: TrackingPositionStatus.fix,
      source: fix.source,
      recordedAt: fix.timestamp,
      pendingSync: true,
      latitude: fix.latitude,
      longitude: fix.longitude,
      accuracyMeters: fix.accuracyMeters,
      speedKmh: fix.speedKmh,
      headingDegrees: fix.headingDegrees,
    );
    await _local.enqueue(position, synced: false);
    return position;
  }

  /// Enfile un heartbeat « GPS indisponible » (sans coordonnées) — voir
  /// `TrackingPositionStatus.gpsUnavailable`.
  Future<TrackingPosition> enqueueGpsUnavailable(String departureUuid) async {
    final int sequence = await _local.nextSequence();
    final TrackingPosition position = TrackingPosition(
      positionUuid: _uuid.v4(),
      departureUuid: departureUuid,
      sequence: sequence,
      status: TrackingPositionStatus.gpsUnavailable,
      source: TrackingPositionSource.unknown,
      recordedAt: DateTime.now(),
      pendingSync: true,
    );
    await _local.enqueue(position, synced: false);
    return position;
  }

  /// Envoie la file en attente de [departureUuid] par lots. Idempotent :
  /// rejouer après une coupure ne crée jamais de doublon côté serveur
  /// (`position_uuid`). Chaque position est marquée synchronisée dès que
  /// le serveur a statué dessus, qu'elle ait été acceptée, déjà connue
  /// (doublon) ou rejetée définitivement (horodatage impossible) — dans
  /// les trois cas, la réessayer n'aurait aucun effet utile. Seule une
  /// coupure réseau AVANT toute réponse laisse le lot entier `pending`,
  /// sans qu'aucun point ne soit perdu (critère d'acceptation MEZ-101).
  Future<void> flush(String departureUuid) async {
    if (_flushing || !_online) return;
    _flushing = true;

    try {
      final List<TrackingPosition> pending =
          await _local.listPending(departureUuid);
      if (pending.isEmpty) return;

      for (int i = 0; i < pending.length; i += batchChunkSize) {
        final List<TrackingPosition> chunk =
            pending.sublist(i, math.min(i + batchChunkSize, pending.length));

        await _remote.sendBatch(departureUuid: departureUuid, positions: chunk);
        for (final TrackingPosition position in chunk) {
          await _local.markSynced(position.positionUuid);
        }
      }

      await _local.pruneSynced(olderThan: const Duration(days: 2));
    } on DioException catch (error) {
      if (_isNetworkUnreachable(error)) _online = false;
      // Toute autre erreur (auth, serveur, validation) : les positions
      // restent en file, retentées au prochain appel — aucune n'est
      // perdue.
    } finally {
      _flushing = false;
    }
  }

  /// À appeler quand la connectivité système revient — réarme [isOnline]
  /// pour que le prochain [flush] soit tenté (sinon, une fois passé hors
  /// ligne suite à une erreur réseau, plus aucun flush ne serait tenté).
  void markOnline() => _online = true;

  void markOffline() => _online = false;

  Future<int> pendingCount(String departureUuid) => _local
      .listPending(departureUuid)
      .then((List<TrackingPosition> l) => l.length);

  bool _isNetworkUnreachable(DioException error) =>
      error.type == DioExceptionType.connectionError ||
      error.type == DioExceptionType.connectionTimeout ||
      error.type == DioExceptionType.receiveTimeout ||
      error.type == DioExceptionType.sendTimeout;
}
