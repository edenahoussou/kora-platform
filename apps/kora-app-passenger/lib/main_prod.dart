import 'bootstrap.dart';
import 'core/env/app_env.dart';

/// Point d'entrée PROD. **C'est celui-ci qu'il faut utiliser pour tout
/// build destiné au Play Store / App Store.**
///
/// ```bash
/// flutter build apk -t lib/main_prod.dart --release
/// flutter build ipa -t lib/main_prod.dart --release
/// ```
void main() => bootstrap(AppEnv.prod);
