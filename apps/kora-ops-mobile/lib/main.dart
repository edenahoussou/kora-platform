import 'package:flutter/material.dart';
import 'app/app.dart';
import 'app/config/app_config.dart';
import 'injection/injection.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  AppConfig.init();
  await configureDependencies();

  runApp(const App());
}
