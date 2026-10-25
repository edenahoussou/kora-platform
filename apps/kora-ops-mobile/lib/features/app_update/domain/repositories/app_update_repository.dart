import '../../../../core/result/result.dart';
import '../../data/services/apk_installer.dart';
import '../entities/app_update_info.dart';
import '../entities/app_version.dart';

/// Couche DOMAIN : distribution hors store de KORA OPS Mobile (MEZ-150) —
/// vérifier, télécharger, contrôler et installer une nouvelle version.
abstract class AppUpdateRepository {
  /// Version installée sur l'appareil (`null` hors Android).
  Future<AppVersion?> installedVersion();

  /// Vérification au lancement : `Success(null)` si aucune version n'est
  /// publiée côté serveur.
  Future<Result<AppUpdateInfo?>> check(AppVersion? installed);

  /// Dernier bloc `update` connu (cache local) — `null` si aucun.
  Future<AppUpdateInfo?> lastKnown();

  /// Mémorise le dernier bloc reçu ; `null` l'efface.
  Future<void> remember(AppUpdateInfo? info);

  /// Télécharge l'APK de [info] puis vérifie son sha256 ; retourne le
  /// chemin du fichier prêt à installer. [onProgress] reçoit une fraction
  /// entre 0 et 1 (ou `null` si la taille est inconnue).
  Future<Result<String>> downloadAndVerify(
    AppUpdateInfo info, {
    void Function(double? progress)? onProgress,
  });

  Future<Result<ApkInstallOutcome>> install(String apkPath);

  Future<void> openInstallPermissionSettings();

  /// La proposition non obligatoire de [info] a-t-elle été reportée ?
  Future<bool> isSnoozed(AppUpdateInfo info);

  /// « Plus tard » : ne plus proposer ce build avant [duration].
  Future<void> snooze(AppUpdateInfo info, Duration duration);
}
