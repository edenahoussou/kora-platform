import 'package:flutter/material.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'app.dart';
import 'core/di/injector.dart';
import 'core/env/app_env.dart';
import 'core/utils/date_formats.dart';

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

  // Charge les noms de mois/jours français utilisés par `KoraDateFormats`.
  // Sans cet appel, le premier `DateFormat(..., 'fr_FR')` lèverait une
  // exception « locale data has not been initialized ».
  await initializeDateFormatting(KoraDateFormats.locale);

  // Enregistre toutes les dépendances (data sources, repositories, blocs)
  // dans le service locator get_it. Voir core/di/injector.dart.
  await setupInjector();

  runApp(const KoraApp());
}
