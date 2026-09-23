import 'package:equatable/equatable.dart';

/// Snapshot de l'état du suivi une fois actif, envoyé par
/// `TrackingTaskHandler` (isolate du service) à `TrackingRepositoryImpl`
/// (isolate principal) via `FlutterForegroundTask.sendDataToMain` — voir
/// `TrackingCubit`. Distinct de `TrackingAvailability`, qui ne concerne
/// que les conditions de DÉMARRAGE.
class TrackingRuntimeStatus extends Equatable {
  const TrackingRuntimeStatus({
    required this.isOnline,
    required this.isSyncing,
    required this.pendingCount,
    required this.lastPositionAt,
    this.lastError,
  });

  const TrackingRuntimeStatus.initial()
      : isOnline = true,
        isSyncing = false,
        pendingCount = 0,
        lastPositionAt = null,
        lastError = null;

  final bool isOnline;
  final bool isSyncing;
  final int pendingCount;
  final DateTime? lastPositionAt;

  /// Raison du dernier échec d'envoi (`HTTP 403 — {...}`, etc.) — voir
  /// `TrackingSyncEngine.lastError`. `null` tant que rien n'a échoué, ou
  /// juste après un envoi réussi. Distinct de `!isOnline` : une erreur
  /// serveur (permission, validation...) laisse `isOnline` vrai — ce
  /// n'est pas un souci réseau — mais mérite d'être visible aussi.
  final String? lastError;

  /// Reconstruit depuis la charge utile envoyée par le TaskHandler — voir
  /// `TrackingTaskHandler._reportStatus`.
  factory TrackingRuntimeStatus.fromData(Map<Object?, Object?> data) {
    return TrackingRuntimeStatus(
      isOnline: data['isOnline'] as bool? ?? true,
      isSyncing: data['isSyncing'] as bool? ?? false,
      pendingCount: data['pendingCount'] as int? ?? 0,
      lastPositionAt: data['lastPositionAt'] is String
          ? DateTime.tryParse(data['lastPositionAt']! as String)
          : null,
      lastError: data['lastError'] as String?,
    );
  }

  /// Charge utile envoyée par le TaskHandler — voir
  /// `TrackingTaskHandler._reportStatus`.
  Map<String, Object?> toData() => <String, Object?>{
        'isOnline': isOnline,
        'isSyncing': isSyncing,
        'pendingCount': pendingCount,
        'lastPositionAt': lastPositionAt?.toIso8601String(),
        'lastError': lastError,
      };

  TrackingRuntimeStatus copyWith({
    bool? isOnline,
    bool? isSyncing,
    int? pendingCount,
    DateTime? lastPositionAt,
    String? lastError,
  }) =>
      TrackingRuntimeStatus(
        isOnline: isOnline ?? this.isOnline,
        isSyncing: isSyncing ?? this.isSyncing,
        pendingCount: pendingCount ?? this.pendingCount,
        lastPositionAt: lastPositionAt ?? this.lastPositionAt,
        lastError: lastError ?? this.lastError,
      );

  @override
  List<Object?> get props => <Object?>[
        isOnline,
        isSyncing,
        pendingCount,
        lastPositionAt,
        lastError,
      ];
}
