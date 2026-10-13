import 'dart:io';

import 'package:in_app_update/in_app_update.dart';

/// Ce que Google Play sait d'une mise à jour en attente.
class PlayUpdate {
  const PlayUpdate({
    required this.immediateAllowed,
    required this.flexibleAllowed,
    required this.priority,
  });

  final bool immediateAllowed;
  final bool flexibleAllowed;

  /// La priorité donnée à la publication dans la Play Console, de 0 à 5.
  ///
  /// C'est **elle** qui décide si la mise à jour s'impose ou se propose,
  /// et c'est voulu : la décision se prend au moment de publier, là où
  /// l'on sait si la version corrige une broutille ou une panne. Pas
  /// besoin d'un réglage serveur en plus.
  final int priority;
}

/// La frontière entre l'app et Google Play.
///
/// **Android seulement.** Apple ne publie aucune API de mise à jour :
/// sur iOS, rien ne se déclenche, et la question de la forme à donner s'y
/// posera le jour où on s'en occupera.
///
/// Isolée derrière cette interface, la décision — imposer ou proposer —
/// se teste sans boutique.
abstract class UpdateGateway {
  /// Vrai là où la mise à jour en place existe.
  bool get isSupported;

  /// La mise à jour en attente, ou `null` s'il n'y en a pas — ou si Play
  /// n'a pas répondu.
  Future<PlayUpdate?> pending();

  /// Play occupe l'écran jusqu'à l'installation.
  Future<void> startImmediate();

  /// Play télécharge en tâche de fond, puis demande à redémarrer.
  Future<void> startFlexible();
}

class PlayStoreUpdateGateway implements UpdateGateway {
  const PlayStoreUpdateGateway();

  @override
  bool get isSupported => Platform.isAndroid;

  @override
  Future<PlayUpdate?> pending() async {
    if (!isSupported) return null;

    try {
      final info = await InAppUpdate.checkForUpdate();
      if (info.updateAvailability != UpdateAvailability.updateAvailable) {
        return null;
      }

      return PlayUpdate(
        immediateAllowed: info.immediateUpdateAllowed,
        flexibleAllowed: info.flexibleUpdateAllowed,
        priority: info.updatePriority,
      );
    } on Exception {
      // Play indisponible, application installée hors boutique, appareil
      // sans services Google : il n'y a rien à proposer, et rien à dire.
      return null;
    }
  }

  @override
  Future<void> startImmediate() => InAppUpdate.performImmediateUpdate();

  @override
  Future<void> startFlexible() async {
    await InAppUpdate.startFlexibleUpdate();
    await InAppUpdate.completeFlexibleUpdate();
  }
}
