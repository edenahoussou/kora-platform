/// Couche DOMAIN (Clean Architecture) : une entité, c'est-à-dire un
/// concept métier pur, sans aucune dépendance à Flutter ni à une librairie
/// externe. C'est la couche la plus "protégée" du projet.
///
/// Les 3 modes possibles pour le thème de l'application.
enum AppThemeMode {
  light,
  dark,

  /// Suit automatiquement le réglage du téléphone.
  system;

  /// Convertit une chaîne de caractères stockée (ex: venant de
  /// SharedPreferences) vers une valeur [AppThemeMode].
  ///
  /// **Le clair est le défaut**, pas le réglage du téléphone. KORA est
  /// une app qu'on ouvre en gare, souvent en plein jour : suivre un
  /// téléphone réglé en sombre donnait un premier écran que le soleil
  /// rend illisible. Celui qui préfère le sombre le choisit une fois, et
  /// son choix est relu ici.
  static AppThemeMode fromStorageValue(String? value) {
    switch (value) {
      case 'dark':
        return AppThemeMode.dark;
      case 'system':
        return AppThemeMode.system;
      default:
        return AppThemeMode.light;
    }
  }

  /// Convertit la valeur en chaîne pour la sauvegarder.
  String toStorageValue() => name;
}
