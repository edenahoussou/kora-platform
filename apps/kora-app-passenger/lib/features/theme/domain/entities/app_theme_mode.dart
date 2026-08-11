/// Couche DOMAIN (Clean Architecture) : une entité, c'est-à-dire un
/// concept métier pur, sans aucune dépendance à Flutter ni à une librairie
/// externe. C'est la couche la plus "protégée" du projet.
///
/// Les 3 modes possibles pour le thème de l'application.
enum AppThemeMode {
  light,
  dark,

  /// Suit automatiquement le réglage du téléphone (le plus courant par
  /// défaut sur une app professionnelle).
  system;

  /// Convertit une chaîne de caractères stockée (ex: venant de
  /// SharedPreferences) vers une valeur [AppThemeMode].
  static AppThemeMode fromStorageValue(String? value) {
    switch (value) {
      case 'light':
        return AppThemeMode.light;
      case 'dark':
        return AppThemeMode.dark;
      default:
        return AppThemeMode.system;
    }
  }

  /// Convertit la valeur en chaîne pour la sauvegarder.
  String toStorageValue() => name;
}
