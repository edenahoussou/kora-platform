/// Les 4 états requis par le ticket (MEZ-87) pour une libération de
/// siège (descente anticipée ou no-show) — `error` est un 5e état
/// technique, comme pour `BoardingOutcomeStatus`.
enum SeatReleaseOutcomeStatus {
  accepted,
  conflict,
  rejected,
  pendingSync,
  error;

  String get defaultMessage => switch (this) {
        SeatReleaseOutcomeStatus.accepted => 'Libération enregistrée.',
        SeatReleaseOutcomeStatus.conflict =>
          'Ce trajet a déjà été libéré depuis un autre terminal.',
        SeatReleaseOutcomeStatus.rejected => 'Libération refusée.',
        SeatReleaseOutcomeStatus.pendingSync =>
          'Enregistré hors ligne — en attente de synchronisation.',
        SeatReleaseOutcomeStatus.error => 'Une erreur est survenue.',
      };

  /// `true` pour un résultat métier conclusif — bloque immédiatement une
  /// seconde tentative sur le même billet, y compris hors ligne (même
  /// principe que `BoardingOutcomeStatus.isConclusive`).
  bool get isConclusive => this != SeatReleaseOutcomeStatus.error;
}
