import 'package:equatable/equatable.dart';

/// Gravité — miroir de `OpsNotificationSeverity` côté backend.
/// [critical] = alerte bloquante (révocation de terminal, conflit de
/// scan...), [warning] = erreur récupérable, [info] = information de
/// parcours (voir MEZ-97, critère "distinguer les alertes bloquantes, les
/// erreurs récupérables et les informations de parcours").
enum OpsNotificationSeverity { info, warning, critical }

extension OpsNotificationSeverityX on OpsNotificationSeverity {
  static OpsNotificationSeverity fromValue(String? value) => switch (value) {
        'critical' => OpsNotificationSeverity.critical,
        'warning' => OpsNotificationSeverity.warning,
        _ => OpsNotificationSeverity.info,
      };

  String get value => switch (this) {
        OpsNotificationSeverity.critical => 'critical',
        OpsNotificationSeverity.warning => 'warning',
        OpsNotificationSeverity.info => 'info',
      };
}

/// Action proposée au convoyeur — miroir de `OpsNotificationAction` côté
/// backend. Dicte où [NotificationCubit]/`NotificationsPage` redirigent au
/// tap (voir MEZ-97, critère "rejoindre directement le manifeste, la file
/// de synchronisation ou l'écran d'authentification concerné").
enum OpsNotificationAction {
  none,
  openDeparture,
  refreshManifest,
  reauthenticate,
  reviewScanConflict,
  reviewRejectedOperations,
  retrySync,
  checkPrinter;

  static OpsNotificationAction fromValue(String? value) => switch (value) {
        'open_departure' => OpsNotificationAction.openDeparture,
        'refresh_manifest' => OpsNotificationAction.refreshManifest,
        'reauthenticate' => OpsNotificationAction.reauthenticate,
        'review_scan_conflict' => OpsNotificationAction.reviewScanConflict,
        'review_rejected_operations' =>
          OpsNotificationAction.reviewRejectedOperations,
        'retry_sync' => OpsNotificationAction.retrySync,
        'check_printer' => OpsNotificationAction.checkPrinter,
        _ => OpsNotificationAction.none,
      };
}

/// Départ associé à la notification, réduit au strict nécessaire pour la
/// navigation/l'affichage (aucun montant, aucune info hors du départ — voir
/// critère MEZ-97 "ne rendre aucun montant, prix ou information hors du
/// départ autorisé").
class OpsNotificationDeparture extends Equatable {
  const OpsNotificationDeparture({
    required this.uuid,
    this.lineCode,
    this.date,
    this.time,
  });

  final String uuid;
  final String? lineCode;
  final String? date;
  final String? time;

  @override
  List<Object?> get props => <Object?>[uuid, lineCode, date, time];
}

class OpsNotification extends Equatable {
  const OpsNotification({
    required this.uuid,
    required this.type,
    required this.severity,
    required this.title,
    required this.message,
    required this.action,
    required this.isRead,
    required this.createdAt,
    this.eventUuid,
    this.readAt,
    this.occurredAt,
    this.departure,
    this.data = const <String, dynamic>{},
  });

  final String uuid;

  /// Code brut (`scan_conflict`, `manifest_stale`...) — voir
  /// `OpsNotificationType` côté backend. Gardé en `String` plutôt qu'un
  /// enum Dart : le backend peut ajouter de nouveaux types (déjà vu avec
  /// `incident_reported`/`departure_departed`) sans casser le parsing ici.
  final String type;
  final OpsNotificationSeverity severity;
  final String title;
  final String message;
  final OpsNotificationAction action;
  final bool isRead;
  final DateTime createdAt;
  final String? eventUuid;
  final DateTime? readAt;
  final DateTime? occurredAt;
  final OpsNotificationDeparture? departure;
  final Map<String, dynamic> data;

  OpsNotification copyWith({
    bool? isRead,
    DateTime? readAt,
  }) =>
      OpsNotification(
        uuid: uuid,
        type: type,
        severity: severity,
        title: title,
        message: message,
        action: action,
        isRead: isRead ?? this.isRead,
        createdAt: createdAt,
        eventUuid: eventUuid,
        readAt: readAt ?? this.readAt,
        occurredAt: occurredAt,
        departure: departure,
        data: data,
      );

  @override
  List<Object?> get props => <Object?>[
        uuid,
        type,
        severity,
        title,
        message,
        action,
        isRead,
        createdAt,
        eventUuid,
        readAt,
        occurredAt,
        departure,
      ];
}
