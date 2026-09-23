/// Raison pour laquelle le suivi n'est pas actif, à afficher au convoyeur
/// (ticket MEZ-101, critère « le convoyeur sait pourquoi le suivi est
/// indisponible et peut corriger l'autorisation ou le GPS »). `ready` :
/// rien ne bloque, mais le suivi n'a pas encore été démarré (aucun départ
/// `departed` sélectionné). Ne concerne QUE les conditions de démarrage —
/// une fois actif, l'état réseau/synchro est porté séparément par
/// `TrackingState.isOnline`/`isSyncing` (le suivi continue localement même
/// hors ligne).
enum TrackingAvailability {
  ready,
  permissionDenied,
  gpsDisabled,
  insufficientAccuracy;

  String get label => switch (this) {
        TrackingAvailability.ready => 'Prêt',
        TrackingAvailability.permissionDenied =>
          'Autorisation de localisation refusée',
        TrackingAvailability.gpsDisabled => 'GPS désactivé',
        TrackingAvailability.insufficientAccuracy =>
          'Précision de localisation insuffisante',
      };

  /// Ce que le convoyeur peut faire pour corriger — vide pour `ready`.
  String get correctiveAction => switch (this) {
        TrackingAvailability.ready => '',
        TrackingAvailability.permissionDenied =>
          'Autorisez la localisation pour KORA OPS dans les réglages, y '
              'compris « toujours autoriser » pour l\'arrière-plan.',
        TrackingAvailability.gpsDisabled =>
          'Activez la localisation dans les réglages rapides du '
              'téléphone.',
        TrackingAvailability.insufficientAccuracy =>
          'Passez la précision de localisation sur « Précise » dans les '
              'réglages Android.',
      };
}
