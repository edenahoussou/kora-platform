import 'package:shared_preferences/shared_preferences.dart';

import '../../constants/storage_keys.dart';

/// Mémorise le dernier couple (appareil, token FCM) confirmé par le
/// backend. Sur P501 (souvent hors ligne), un `PUT push-token` raté est
/// rejoué au retour du réseau : on compare simplement le token courant à
/// celui confirmé ici, sans file d'attente dédiée.
class PushTokenStore {
  const PushTokenStore(this._preferences);

  final SharedPreferences _preferences;

  bool get hasRegistration =>
      _preferences.getString(StorageKeys.pushTokenRegistration) != null;

  bool isRegistered(String deviceUuid, String token) =>
      _preferences.getString(StorageKeys.pushTokenRegistration) ==
      _key(deviceUuid, token);

  Future<void> markRegistered(String deviceUuid, String token) =>
      _preferences.setString(
          StorageKeys.pushTokenRegistration, _key(deviceUuid, token));

  Future<void> clear() =>
      _preferences.remove(StorageKeys.pushTokenRegistration);

  String _key(String deviceUuid, String token) => '$deviceUuid|$token';
}
