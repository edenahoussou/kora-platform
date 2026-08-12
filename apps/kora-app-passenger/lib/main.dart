import 'main_dev.dart' as entrypoint;

/// Alias de confort : `flutter run` (sans `-t`) utilise `lib/main.dart`
/// par défaut. On redirige simplement vers l'environnement DEV pour que
/// le flux de développement au quotidien reste `flutter run` tout court.
///
/// Pour staging/prod, cible explicitement le bon point d'entrée :
/// `flutter run -t lib/main_staging.dart` ou `lib/main_prod.dart`
/// (voir ces fichiers, et `lib/core/env/app_env.dart`).
void main() => entrypoint.main();
