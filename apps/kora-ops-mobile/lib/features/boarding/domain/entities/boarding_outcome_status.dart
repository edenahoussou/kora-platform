/// Les 7 états requis par le ticket (MEZ-91) — chacun doit s'afficher en
/// moins d'une seconde. [error] est un 8e état technique (réseau/serveur
/// hors des cas métier ci-dessous) : non demandé par le ticket, mais une
/// panne serveur ne peut pas rester sans retour pour l'agent.
enum BoardingOutcomeStatus {
  valid,
  wrongDeparture,
  ticketUnknown,
  alreadyBoarded,
  cancelled,
  conflict,
  pendingSync,
  error;

  /// Message par défaut, utilisé quand le serveur n'en fournit pas
  /// (`qr_unknown` n'a pas de message métier dédié par ticket, par ex.) ou
  /// pour les états purement locaux (`pendingSync`).
  String get defaultMessage => switch (this) {
        BoardingOutcomeStatus.valid => 'Billet valide.',
        BoardingOutcomeStatus.wrongDeparture =>
          'Ce billet ne correspond pas à ce départ.',
        BoardingOutcomeStatus.ticketUnknown =>
          'Billet inconnu ou signature invalide.',
        BoardingOutcomeStatus.alreadyBoarded => 'Billet déjà embarqué.',
        BoardingOutcomeStatus.cancelled => 'Ce billet a été annulé.',
        BoardingOutcomeStatus.conflict =>
          'Ce billet a déjà été embarqué sur un autre terminal.',
        BoardingOutcomeStatus.pendingSync =>
          'Enregistré hors ligne — en attente de synchronisation.',
        BoardingOutcomeStatus.error => 'Une erreur est survenue.',
      };

  /// `true` pour les états qui bloquent immédiatement un second scan du
  /// même billet sur ce terminal, y compris hors ligne (voir critère
  /// d'acceptation MEZ-91) — un billet dont le scan a échoué techniquement
  /// ([error]) reste re-scannable, ce n'est pas un résultat métier.
  bool get isConclusive => this != BoardingOutcomeStatus.error;
}
