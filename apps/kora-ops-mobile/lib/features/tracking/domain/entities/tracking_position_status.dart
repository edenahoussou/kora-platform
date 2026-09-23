/// `fix` : coordonnées réelles. `gpsUnavailable` : heartbeat envoyé
/// immédiatement quand le GPS est désactivé ou le signal perdu — sans
/// coordonnées, juste pour que le serveur sache que le terminal est
/// toujours actif (voir MEZ-100/MEZ-101, `TrackingPositionStatus` côté
/// backend). Le nom Dart `fix`/`gpsUnavailable` diffère volontairement de
/// la valeur JSON `fix`/`gps_unavailable` (camelCase Dart, snake_case
/// contrat) — voir [value].
enum TrackingPositionStatus {
  fix('fix'),
  gpsUnavailable('gps_unavailable');

  const TrackingPositionStatus(this.value);

  /// Valeur exacte attendue par le contrat MEZ-100 (`positions[].status`).
  final String value;

  static TrackingPositionStatus fromValue(String? value) =>
      TrackingPositionStatus.values.firstWhere(
        (TrackingPositionStatus s) => s.value == value,
        orElse: () => fix,
      );
}
