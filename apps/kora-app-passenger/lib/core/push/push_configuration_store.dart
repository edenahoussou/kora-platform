import 'package:shared_preferences/shared_preferences.dart';

import '../constants/storage_keys.dart';
import 'firebase_configuration.dart';

/// La configuration Firebase conservée sur l'appareil.
///
/// **C'est l'isolat de fond qui justifie ce cache.** Quand une
/// notification arrive alors que l'application est fermée, Android
/// réveille un isolat Dart neuf, sans session, sans `Dio` et parfois sans
/// réseau. Cet isolat doit pourtant initialiser Firebase avant de lire
/// quoi que ce soit — il n'a donc d'autre choix que de relire une
/// configuration déjà posée sur l'appareil.
///
/// On passe par [SharedPreferences] et non par le stockage sécurisé :
/// ces valeurs sont publiques (voir [FirebaseConfiguration]), et les
/// préférences simples se relisent sans difficulté depuis un isolat.
class PushConfigurationStore {
  const PushConfigurationStore();

  Future<void> save(FirebaseConfiguration configuration) async {
    final preferences = await SharedPreferences.getInstance();
    await preferences.setString(
      StorageKeys.firebaseConfiguration,
      configuration.encode(),
    );
  }

  /// La dernière configuration connue, ou `null` si l'app n'en a jamais
  /// reçu — premier lancement, ou intégration désactivée côté OPS.
  Future<FirebaseConfiguration?> read() async {
    final preferences = await SharedPreferences.getInstance();

    return FirebaseConfiguration.decode(
      preferences.getString(StorageKeys.firebaseConfiguration),
    );
  }

  /// Oublie la configuration.
  ///
  /// Appelé quand le serveur annonce l'intégration désactivée : garder
  /// l'ancienne ferait tourner un isolat de fond contre un projet
  /// Firebase que KORA n'utilise plus.
  Future<void> clear() async {
    final preferences = await SharedPreferences.getInstance();
    await preferences.remove(StorageKeys.firebaseConfiguration);
  }
}
