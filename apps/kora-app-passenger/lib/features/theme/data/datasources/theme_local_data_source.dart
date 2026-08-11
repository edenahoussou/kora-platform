import 'package:shared_preferences/shared_preferences.dart';

/// Couche DATA (Clean Architecture) : parle directement à une "vraie"
/// source de données. Ici, `SharedPreferences` (stockage clé/valeur simple
/// sur le téléphone). C'est la SEULE classe de tout le projet qui doit
/// connaître `shared_preferences` — si demain on veut changer pour une
/// autre solution de stockage, on ne modifie QUE ce fichier.
abstract class ThemeLocalDataSource {
  /// Retourne le mode sauvegardé : 'light', 'dark', 'system', ou null si
  /// l'utilisateur n'a jamais choisi (première ouverture de l'app).
  Future<String?> getSavedThemeMode();

  /// Sauvegarde le choix de l'utilisateur.
  Future<void> saveThemeMode(String mode);
}

class ThemeLocalDataSourceImpl implements ThemeLocalDataSource {
  ThemeLocalDataSourceImpl(this._prefs);

  final SharedPreferences _prefs;

  static const _themeModeKey = 'kora_theme_mode';

  @override
  Future<String?> getSavedThemeMode() async {
    return _prefs.getString(_themeModeKey);
  }

  @override
  Future<void> saveThemeMode(String mode) async {
    await _prefs.setString(_themeModeKey, mode);
  }
}
