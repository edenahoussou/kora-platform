import 'package:shared_preferences/shared_preferences.dart';

/// Couche DATA : la seule classe qui sait que la langue est rangée dans
/// `SharedPreferences`. Même découpage que `ThemeLocalDataSource`.
abstract class LanguageLocalDataSource {
  /// Code de la langue sauvegardée, ou `null` à la première ouverture.
  Future<String?> getSavedLanguageCode();

  Future<void> saveLanguageCode(String code);
}

class LanguageLocalDataSourceImpl implements LanguageLocalDataSource {
  LanguageLocalDataSourceImpl(this._prefs);

  final SharedPreferences _prefs;

  static const _languageKey = 'kora_language';

  @override
  Future<String?> getSavedLanguageCode() async =>
      _prefs.getString(_languageKey);

  @override
  Future<void> saveLanguageCode(String code) async {
    await _prefs.setString(_languageKey, code);
  }
}
