import 'dart:io';

import 'package:flutter/services.dart';

import '../../domain/entities/app_version.dart';

/// Résultat d'une demande d'installation.
enum ApkInstallOutcome {
  /// L'installeur système Android est affiché : l'utilisateur confirme,
  /// Android remplace l'app puis la relance au prochain lancement.
  installerStarted,

  /// Android 8+ : KORA OPS n'est pas encore autorisée à installer des
  /// applications — ouvrir [ApkInstaller.openInstallPermissionSettings].
  permissionRequired,
}

/// Couche DATA : pont vers le canal natif `kora.ops/apk_installer`
/// (`ApkInstallerChannel.kt`, MEZ-150). Isolé derrière une interface pour
/// que le cubit et les écrans restent testables sans plateforme.
abstract class ApkInstaller {
  /// Version réellement installée (`versionName` + `versionCode`), lue
  /// auprès d'Android — `null` hors Android ou en cas d'erreur.
  Future<AppVersion?> installedVersion();

  /// Répertoire où écrire l'APK (partagé avec l'installeur système par le
  /// FileProvider) — `null` si l'installation d'APK n'est pas possible.
  Future<String?> updatesDirectory();

  Future<bool> canRequestPackageInstalls();

  Future<void> openInstallPermissionSettings();

  Future<ApkInstallOutcome> install(String apkPath);
}

class MethodChannelApkInstaller implements ApkInstaller {
  MethodChannelApkInstaller({MethodChannel? channel, bool? isAndroid})
      : _channel = channel ?? const MethodChannel('kora.ops/apk_installer'),
        _isAndroid = isAndroid ?? Platform.isAndroid;

  final MethodChannel _channel;
  final bool _isAndroid;

  @override
  Future<AppVersion?> installedVersion() async {
    if (!_isAndroid) return null;

    try {
      final Map<Object?, Object?>? info =
          await _channel.invokeMapMethod<Object?, Object?>('installedVersion');
      final Object? name = info?['versionName'];
      final Object? code = info?['versionCode'];

      return AppVersion.tryParse(name is String ? name : null)
          ?.withBuild(code is int ? code : null);
    } on PlatformException {
      return null;
    } on MissingPluginException {
      return null;
    }
  }

  @override
  Future<String?> updatesDirectory() async {
    if (!_isAndroid) return null;

    try {
      return await _channel.invokeMethod<String>('cacheDirectory');
    } on PlatformException {
      return null;
    } on MissingPluginException {
      return null;
    }
  }

  @override
  Future<bool> canRequestPackageInstalls() async {
    if (!_isAndroid) return false;

    return await _channel.invokeMethod<bool>('canRequestPackageInstalls') ??
        false;
  }

  @override
  Future<void> openInstallPermissionSettings() async {
    if (!_isAndroid) return;

    await _channel.invokeMethod<bool>('openInstallPermissionSettings');
  }

  @override
  Future<ApkInstallOutcome> install(String apkPath) async {
    final String? outcome = await _channel.invokeMethod<String>(
      'installApk',
      <String, Object?>{'path': apkPath},
    );

    return outcome == 'permission_required'
        ? ApkInstallOutcome.permissionRequired
        : ApkInstallOutcome.installerStarted;
  }
}
