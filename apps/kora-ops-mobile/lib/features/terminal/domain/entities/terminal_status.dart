/// Miroir de `App\Modules\IdentityAccess\Data\TerminalP501Status` côté
/// backend. `lost` et `maintenance` ne devraient normalement jamais être
/// reçus après un handshake réussi (`HandshakeTerminalAction` les rejette
/// déjà en amont) — conservés ici pour rester fidèles au contrat et pour
/// `acknowledgeSync`/l'affichage dans `TerminalSetupPage`, qui peuvent les
/// recevoir entre deux handshakes.
enum TerminalStatus {
  active,
  inactive,
  maintenance,
  lost;

  static TerminalStatus fromValue(String? value) => switch (value) {
        'active' => TerminalStatus.active,
        'inactive' => TerminalStatus.inactive,
        'maintenance' => TerminalStatus.maintenance,
        'lost' => TerminalStatus.lost,
        _ => TerminalStatus.inactive,
      };

  String get value => switch (this) {
        TerminalStatus.active => 'active',
        TerminalStatus.inactive => 'inactive',
        TerminalStatus.maintenance => 'maintenance',
        TerminalStatus.lost => 'lost',
      };
}
