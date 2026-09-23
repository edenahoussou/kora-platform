import 'package:flutter/material.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';
import 'app/app.dart';
import 'app/config/app_config.dart';
import 'injection/injection.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Port de communication isolate service ↔ isolate principal (MEZ-101) —
  // requis par flutter_foreground_task AVANT tout autre appel du plugin,
  // y compris un `startService` déclenché depuis `TrackingRepositoryImpl.
  // bootstrap()` plus loin dans `configureDependencies()`/`SplashPage`.
  // Le point d'entrée du service lui-même (`trackingStartCallback`, une
  // fonction top-level annotée `@pragma('vm:entry-point')`) vit dans
  // `features/tracking/data/tracking_task_handler.dart` — c'est cette
  // annotation, pas son emplacement, qui la rend résolvable par l'isolate
  // du service ; rien de plus à faire ici.
  FlutterForegroundTask.initCommunicationPort();

  AppConfig.init();
  await configureDependencies();

  runApp(const App());
}
