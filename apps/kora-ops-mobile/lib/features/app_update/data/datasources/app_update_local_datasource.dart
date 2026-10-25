import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../../../core/constants/storage_keys.dart';

/// Couche DATA (MEZ-150) :
///  - mémorise le dernier bloc `update` reçu du serveur, pour qu'une mise à
///    jour obligatoire reste bloquante même si l'app est relancée hors
///    ligne (couper le réseau ne doit pas suffire à contourner le blocage) ;
///  - mémorise le report (« Plus tard ») d'une mise à jour non
///    obligatoire, par build.
abstract class AppUpdateLocalDataSource {
  Future<Map<String, dynamic>?> readLastKnownBlock();

  /// `null` efface le bloc (aucune release publiée côté serveur).
  Future<void> saveLastKnownBlock(Map<String, dynamic>? block);

  Future<DateTime?> snoozedUntil(int build);

  Future<void> snooze(int build, Duration duration);
}

class AppUpdateLocalDataSourceImpl implements AppUpdateLocalDataSource {
  AppUpdateLocalDataSourceImpl(this._preferences, {DateTime Function()? now})
      : _now = now ?? DateTime.now;

  final SharedPreferences _preferences;
  final DateTime Function() _now;

  @override
  Future<Map<String, dynamic>?> readLastKnownBlock() async {
    final String? raw = _preferences.getString(StorageKeys.appUpdateLastBlock);
    if (raw == null || raw.isEmpty) return null;

    try {
      final Object? decoded = jsonDecode(raw);
      return decoded is Map ? Map<String, dynamic>.from(decoded) : null;
    } on FormatException {
      return null;
    }
  }

  @override
  Future<void> saveLastKnownBlock(Map<String, dynamic>? block) async {
    if (block == null) {
      await _preferences.remove(StorageKeys.appUpdateLastBlock);
      return;
    }

    await _preferences.setString(
      StorageKeys.appUpdateLastBlock,
      jsonEncode(block),
    );
  }

  @override
  Future<DateTime?> snoozedUntil(int build) async {
    if (_preferences.getInt(StorageKeys.appUpdateSnoozedBuild) != build) {
      return null;
    }

    final int? until = _preferences.getInt(StorageKeys.appUpdateSnoozedUntil);
    return until == null ? null : DateTime.fromMillisecondsSinceEpoch(until);
  }

  @override
  Future<void> snooze(int build, Duration duration) async {
    await _preferences.setInt(StorageKeys.appUpdateSnoozedBuild, build);
    await _preferences.setInt(
      StorageKeys.appUpdateSnoozedUntil,
      _now().add(duration).millisecondsSinceEpoch,
    );
  }
}
