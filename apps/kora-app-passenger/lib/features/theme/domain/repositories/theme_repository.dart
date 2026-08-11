import '../entities/app_theme_mode.dart';

/// Couche DOMAIN : contrat abstrait ("interface") du repository de thème.
///
/// Pourquoi une interface abstraite et pas directement la classe qui parle
/// à SharedPreferences ? Parce que le Bloc (couche presentation) ne doit
/// JAMAIS dépendre d'un détail technique (SharedPreferences, Hive, une API,
/// peu importe). Il dépend seulement de ce contrat. Ça permet, par exemple,
/// d'écrire un faux repository ("mock") très facilement pour les tests.
abstract class ThemeRepository {
  Future<AppThemeMode> getThemeMode();
  Future<void> setThemeMode(AppThemeMode mode);
}
