import 'bootstrap.dart';
import 'core/env/app_env.dart';

/// Point d'entrée STAGING.
///
/// ```bash
/// flutter run -t lib/main_staging.dart
/// flutter build apk -t lib/main_staging.dart --flavor staging  # si des flavors natifs sont ajoutés un jour
/// ```
void main() => bootstrap(AppEnv.staging);
