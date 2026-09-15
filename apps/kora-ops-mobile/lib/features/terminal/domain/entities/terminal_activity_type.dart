/// Types acceptés par l'endpoint générique `ops-mobile/terminal/activity`
/// (voir `TerminalActivityRequest` côté backend). `handshake` et `sync` en
/// sont volontairement exclus : ils ont chacun leur propre endpoint dédié,
/// qui journalise *et* met à jour un champ du terminal — les accepter ici
/// créerait une entrée d'audit sans la mise à jour correspondante.
enum TerminalActivityType {
  scan,
  print,
  error;

  String get value => switch (this) {
        TerminalActivityType.scan => 'scan',
        TerminalActivityType.print => 'print',
        TerminalActivityType.error => 'error',
      };

  static TerminalActivityType fromValue(String value) => switch (value) {
        'print' => TerminalActivityType.print,
        'error' => TerminalActivityType.error,
        _ => TerminalActivityType.scan,
      };
}
