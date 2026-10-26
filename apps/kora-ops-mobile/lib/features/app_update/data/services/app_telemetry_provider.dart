import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';

import '../../domain/entities/app_version.dart';
import 'apk_installer.dart';

/// Télémétrie envoyée au handshake et à la synchronisation du terminal
/// (`app_version`, `os_version`, `hardware_model` — voir
/// `TerminalHandshakeRequest`/`TerminalSyncRequest` côté backend).
///
/// MEZ-150 : c'est `app_version` (`1.4.0+42`) qui permet au serveur de
/// calculer le bloc `update` et le badge de version de l'écran Terminaux ;
/// après une mise à jour, la nouvelle version est rapportée dès le
/// handshake suivant.
class AppTelemetryProvider {
  AppTelemetryProvider(this._installer, this._deviceInfo);

  final ApkInstaller _installer;
  final DeviceInfoPlugin _deviceInfo;

  Map<String, String>? _cache;

  /// Champs non nuls uniquement — le serveur ignore les champs absents.
  /// Mis en cache : ces valeurs ne changent pas pendant la vie du process
  /// (une mise à jour relance l'app).
  Future<Map<String, String>> collect() async {
    final Map<String, String>? cached = _cache;
    if (cached != null) return cached;

    final Map<String, String> telemetry = <String, String>{};

    try {
      final AppVersion? version = await _installer.installedVersion();
      if (version != null) telemetry['app_version'] = version.toString();

      if (Platform.isAndroid) {
        final AndroidDeviceInfo info = await _deviceInfo.androidInfo;
        telemetry['os_version'] =
            'Android ${info.version.release} (SDK ${info.version.sdkInt})';
        final String model = '${info.manufacturer} ${info.model}'.trim();
        if (model.isNotEmpty) telemetry['hardware_model'] = model;
      }
    } on Object {
      // Télémétrie best-effort : jamais bloquante pour le handshake.
    }

    return _cache = telemetry;
  }
}
