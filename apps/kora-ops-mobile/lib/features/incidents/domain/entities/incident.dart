import 'package:equatable/equatable.dart';

/// Nature de l'incident — miroir de `IncidentType` côté backend
/// (`app/Modules/FleetLite/Data/IncidentType.php`). Les noms d'enum Dart
/// correspondent exactement aux valeurs du contrat (`.name` == valeur
/// JSON), pas de table de correspondance à maintenir.
enum IncidentType {
  breakdown,
  delay,
  accident,
  security,
  other;

  String get label => switch (this) {
        IncidentType.breakdown => 'Panne',
        IncidentType.delay => 'Retard',
        IncidentType.accident => 'Accident',
        IncidentType.security => 'Sécurité',
        IncidentType.other => 'Autre',
      };

  static IncidentType fromValue(String? value) => IncidentType.values
      .firstWhere((IncidentType t) => t.name == value, orElse: () => other);
}

/// Un signalement terrain (panne, retard, accident, sécurité, autre) fait
/// par le convoyeur depuis OPS Mobile — voir MEZ-98,
/// `OpsIncidentController` côté backend.
class Incident extends Equatable {
  const Incident({
    required this.operationUuid,
    required this.departureUuid,
    required this.type,
    required this.description,
    required this.occurredAt,
    required this.pendingSync,
    this.latitude,
    this.longitude,
  });

  /// Généré côté mobile — clé d'idempotence (`incident_uuid` côté
  /// backend), y compris entre deux tentatives après une coupure réseau.
  final String operationUuid;
  final String departureUuid;
  final IncidentType type;
  final String description;
  final DateTime occurredAt;
  final double? latitude;
  final double? longitude;

  /// `true` tant que l'incident n'a pas été confirmé par le serveur —
  /// mis en file hors ligne (MEZ-98, même convention que
  /// `BoardingOutcomeStatus.pendingSync`).
  final bool pendingSync;

  @override
  List<Object?> get props => <Object?>[
        operationUuid,
        departureUuid,
        type,
        description,
        occurredAt,
        latitude,
        longitude,
        pendingSync,
      ];
}
