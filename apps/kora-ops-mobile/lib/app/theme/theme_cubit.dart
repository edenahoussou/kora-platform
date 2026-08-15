import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Pilote le thème clair/sombre de toute l'application.
///
/// Par défaut : [ThemeMode.system] (bascule automatique selon les réglages
/// de l'appareil). L'utilisateur peut forcer clair/sombre manuellement
/// (ex. bouton dans `HomePage`) — le choix est persisté dans
/// `SharedPreferences` et relu au prochain lancement.
class ThemeCubit extends Cubit<ThemeMode> {
  ThemeCubit(this._preferences) : super(_readInitial(_preferences));

  final SharedPreferences _preferences;

  static const String _prefsKey = 'app.theme_mode';

  static ThemeMode _readInitial(SharedPreferences preferences) {
    final String? stored = preferences.getString(_prefsKey);
    return switch (stored) {
      'light' => ThemeMode.light,
      'dark' => ThemeMode.dark,
      _ => ThemeMode.system,
    };
  }

  Future<void> setLight() => _setMode(ThemeMode.light);
  Future<void> setDark() => _setMode(ThemeMode.dark);
  Future<void> setSystem() => _setMode(ThemeMode.system);

  /// Fait défiler système → clair → sombre → système, pour un bouton unique
  /// de bascule rapide (voir `HomePage`).
  Future<void> cycle() {
    final ThemeMode next = switch (state) {
      ThemeMode.system => ThemeMode.light,
      ThemeMode.light => ThemeMode.dark,
      ThemeMode.dark => ThemeMode.system,
    };
    return _setMode(next);
  }

  Future<void> _setMode(ThemeMode mode) async {
    emit(mode);
    await _preferences.setString(_prefsKey, mode.name);
  }
}
