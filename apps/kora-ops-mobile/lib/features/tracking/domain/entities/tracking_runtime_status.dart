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
  });

  const TrackingRuntimeStatus.initial()
      : isOnline = true,
        isSyncing = false,
        pendingCount = 0,
        lastPositionAt = null;

  final bool isOnline;
  final bool isSyncing;
  final int pendingCount;
  final DateTime? lastPositionAt;

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
    );
  }

  /// Charge utile envoyée par le TaskHandler — voir
  /// `TrackingTaskHandler._reportStatus`.
  Map<String, Object?> toData() => <String, Object?>{
        'isOnline': isOnline,
        'isSyncing': isSyncing,
        'pendingCount': pendingCount,
        'lastPositionAt': lastPositionAt?.toIso8601String(),
      };

  TrackingRuntimeStatus copyWith({
    bool? isOnline,
    bool? isSyncing,
    int? pendingCount,
    DateTime? lastPositionAt,
  }) =>
      TrackingRuntimeStatus(
        isOnline: isOnline ?? this.isOnline,
        isSyncing: isSyncing ?? this.isSyncing,
        pendingCount: pendingCount ?? this.pendingCount,
        lastPositionAt: lastPositionAt ?? this.lastPositionAt,
      );

  @override
  List<Object?> get props =>
      <Object?>[isOnline, isSyncing, pendingCount, lastPositionAt];
}
