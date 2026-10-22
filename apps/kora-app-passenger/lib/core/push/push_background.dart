import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import 'push_configuration_store.dart';

/// Le point d'entrée réveillé par Android quand une notification arrive
/// alors que l'application est en arrière-plan ou fermée.
///
/// **Il tourne dans un isolat neuf.** Ni l'injecteur, ni la session, ni
/// `Dio` n'existent ici : tout ce qui a été construit au démarrage de
/// l'app est absent. D'où l'annotation `vm:entry-point`, sans laquelle le
/// compilateur en mode release supprimerait cette fonction, et d'où la
/// lecture de la configuration Firebase depuis le disque plutôt que
/// depuis le réseau.
///
/// Ce que cet isolat ne fait pas est aussi important que ce qu'il fait :
/// il n'affiche rien. Une notification qui porte un titre et un texte est
/// déjà affichée par le système ; la dupliquer donnerait deux bandeaux
/// pour un seul message.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  final configuration = await const PushConfigurationStore().read();
  final platform = defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android';
  if (configuration == null || !configuration.isUsableOn(platform)) return;

  // Firebase n'existe pas encore dans cet isolat : on le démarre avec la
  // configuration posée sur l'appareil lors d'une session précédente.
  if (Firebase.apps.isEmpty) {
    await Firebase.initializeApp(options: configuration.toOptions());
  }

  // Rien de plus pour l'instant : le serveur n'envoie aucune donnée à
  // traiter, seulement un titre et un texte que le système affiche
  // lui-même. Le jour où il joindra un bloc `data` — identifiant de
  // réservation, type d'événement —, c'est ici qu'on préparera ce que
  // l'app lira à son ouverture.
}
