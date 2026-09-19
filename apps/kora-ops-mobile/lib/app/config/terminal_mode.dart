/// Matériel utilisé par ce build pour le scan et l'impression : un
/// terminal P501 physique, ou la caméra du téléphone (le PDF de
/// confirmation remplace l'impression thermique — voir ticket "Supporter
/// le scan par caméra téléphone en alternative au P501").
///
/// Une même installation ne tourne que dans un seul mode à la fois — ce
/// n'est pas un réglage utilisateur en cours de session, voir
/// [AppConfig.terminalMode].
enum TerminalMode { p501, phone }

/// Override direct dans le code, sans passer par `--dart-define` — pratique
/// en dev (lancement depuis l'IDE sans configurer d'arguments). Laisse à
/// `null` pour garder `--dart-define=TERMINAL_MODE=...` (ou `p501` par
/// défaut si rien n'est passé) — voir [AppConfig.terminalMode].
///
/// À NE PAS committer positionné sur `phone` pour un build de prod : ça
/// prime sur tout, y compris un `--dart-define` explicite.
// const TerminalMode? kTerminalModeOverride = null;
const TerminalMode kTerminalModeOverride = TerminalMode.phone;

/// Même principe pour `USE_MOCK_HARDWARE` — override direct dans le code,
/// sans `--dart-define`. Laisse à `null` pour garder le comportement
/// normal (`--dart-define=USE_MOCK_HARDWARE=...`, ou `true` par défaut
/// hors prod) — voir [AppConfig.useMockHardware].
///
/// À NE PAS committer positionné sur `false` pour un build de prod ou un
/// commit partagé : ça prime sur tout, y compris un `--dart-define`
/// explicite.
// const bool? kUseMockHardwareOverride = null;
const bool kUseMockHardwareOverride = false;

extension TerminalModeX on TerminalMode {
  static TerminalMode fromString(String value) => switch (value.toLowerCase()) {
        'phone' || 'telephone' || 'téléphone' => TerminalMode.phone,
        _ => TerminalMode.p501,
      };
}
