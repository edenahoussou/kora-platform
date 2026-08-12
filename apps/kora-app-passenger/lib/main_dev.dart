import 'bootstrap.dart';
import 'core/env/app_env.dart';

/// Point d'entrée DEV. C'est celui que `flutter run` utilise par défaut
/// (voir `lib/main.dart`).
///
/// ```bash
/// flutter run -t lib/main_dev.dart
/// ```
void main() => bootstrap(AppEnv.dev);
