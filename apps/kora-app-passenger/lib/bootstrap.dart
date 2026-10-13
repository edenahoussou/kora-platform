import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'app.dart';
import 'core/di/injector.dart';
import 'core/env/app_env.dart';
import 'core/l10n/app_localization.dart';
import 'core/push/push_background.dart';

/// Démarrage commun à tous les environnements. Chaque point d'entrée
/// (`main_dev.dart`, `main_staging.dart`, `main_prod.dart`) ne fait qu'un
/// appel à cette fonction avec la config qui lui correspond.
///
/// `WidgetsFlutterBinding.ensureInitialized()` est nécessaire car on
/// utilise des plugins (SharedPreferences) AVANT `runApp` — Flutter a
/// besoin que le "moteur" soit prêt pour ça.
Future<void> bootstrap(AppEnv env) async {
  WidgetsFlutterBinding.ensureInitialized();

  // Doit être fait AVANT setupInjector() : l'injecteur construit le
  // DioClient avec AppEnv.current.apiBaseUrl.
  AppEnv.current = env;

  // Charge les noms de mois et de jours de toutes les langues de l'app.
  // Sans cet appel, le premier `DateFormat(..., 'fr_FR')` lèverait une
  // exception « locale data has not been initialized ». Sans argument,
  // `initializeDateFormatting` les charge toutes : le surcoût est
  // négligeable devant le risque d'oublier une langue ajoutée plus tard.
  await initializeDateFormatting();

  // Lit les fichiers de traduction avant le premier écran : sinon
  // `Localizations` n'affiche rien le temps de la lecture, et l'app
  // démarre sur un blanc.
  await AppLocalization.preloadAll();

  // Enregistre toutes les dépendances (data sources, repositories, blocs)
  // dans le service locator get_it. Voir core/di/injector.dart.
  await setupInjector();

  // Déclaré avant le premier écran : Android réveille ce point d'entrée
  // sans passer par l'application, et il doit être connu de Firebase dès
  // le démarrage. Rien n'est initialisé ici — le handler lit lui-même ce
  // dont il a besoin, puisqu'il tourne dans un isolat séparé.
  FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

  runApp(const KoraApp());
}
