import 'dart:math';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../../core/constants/storage_keys.dart';
import '../../domain/entities/auth_device.dart';
import '../../domain/entities/device_platform.dart';

/// Couche DATA : décrit l'appareil courant pour l'API. `login` exige un
/// `device_fingerprint` **stable** — c'est lui qui dit au serveur « tu me
/// connais déjà », et donc qui évite de redemander un OTP à chaque
/// connexion. Porté de kora-app-passenger.
abstract class DeviceInfoDataSource {
  Future<DeviceRegistration> currentDevice({String? pushToken});

  /// Numéro de série pour l'enregistrement en tant que terminal
  /// (`TerminalP501.type = phone`) — voir `TerminalRepository.
  /// phoneSerialNumber` et le ticket "Supporter le scan par caméra
  /// téléphone en alternative au P501". Volontairement DISTINCT de
  /// l'empreinte d'auth (`_fingerprint`/`device_fingerprint`) : celle-ci
  /// inclut un sel aléatoire régénéré à chaque réinstallation — bon pour
  /// éviter un re-OTP, inadapté pour identifier un terminal (perdrait
  /// l'historique d'affectation à chaque réinstall). Ici, l'identifiant
  /// matériel brut (`androidId`/`identifierForVendor`), stable tant que
  /// l'appareil n'est pas réinitialisé — le plus proche d'un numéro de
  /// série qu'une app puisse lire (aucune plateforme n'expose l'IMEI/le
  /// numéro de série physique à une app tierce).
  Future<String> phoneTerminalSerial();
}

class DeviceInfoDataSourceImpl implements DeviceInfoDataSource {
  DeviceInfoDataSourceImpl(this._deviceInfo, this._storage);

  final DeviceInfoPlugin _deviceInfo;
  final FlutterSecureStorage _storage;

  @override
  Future<DeviceRegistration> currentDevice({String? pushToken}) async {
    final platform = DevicePlatform.current();

    return DeviceRegistration(
      name: await _readName(platform),
      platform: platform,
      fingerprint: await _fingerprint(),
      pushToken: pushToken,
    );
  }

  @override
  Future<String> phoneTerminalSerial() async {
    final DevicePlatform platform = DevicePlatform.current();

    final String rawId = switch (platform) {
      DevicePlatform.android => (await _deviceInfo.androidInfo).id,
      DevicePlatform.ios =>
        (await _deviceInfo.iosInfo).identifierForVendor ?? '',
      DevicePlatform.web || DevicePlatform.unknown => '',
    };

    final String prefix = switch (platform) {
      DevicePlatform.android => 'TEL-ANDROID',
      DevicePlatform.ios => 'TEL-IOS',
      DevicePlatform.web || DevicePlatform.unknown => 'TEL',
    };

    // Nettoyage : le serveur uppercase et valide `max:64` côté
    // enregistrement (voir `RegisterTerminalRequest`) — mais autant lui
    // envoyer quelque chose de propre plutôt qu'un UUID avec tirets.
    final String cleaned =
        rawId.replaceAll(RegExp('[^A-Za-z0-9]'), '').toUpperCase();

    return '$prefix-${cleaned.isEmpty ? 'INCONNU' : cleaned}';
  }

  Future<String> _readName(DevicePlatform platform) async {
    switch (platform) {
      case DevicePlatform.android:
        final info = await _deviceInfo.androidInfo;
        final brand = _capitalize(info.brand);
        final model = info.model.trim();
        if (model.toLowerCase().startsWith(brand.toLowerCase())) return model;

        return '$brand $model'.trim();
      case DevicePlatform.ios:
        final info = await _deviceInfo.iosInfo;
        return info.name.isEmpty ? info.utsname.machine : info.name;
      case DevicePlatform.web:
      case DevicePlatform.unknown:
        return 'Appareil KORA OPS';
    }
  }

  /// Empreinte stable, générée une fois puis relue depuis le stockage
  /// sécurisé — les identifiants système seuls ne sont pas fiables comme
  /// clé unique (l'`androidId` change à une réinitialisation d'usine, par
  /// exemple). Ce qui compte, c'est qu'elle ne bouge pas entre deux
  /// lancements.
  Future<String> _fingerprint() async {
    final stored = await _storage.read(key: StorageKeys.deviceFingerprint);
    if (stored != null && stored.isNotEmpty) return stored;

    final generated = await _generateFingerprint();
    await _storage.write(
      key: StorageKeys.deviceFingerprint,
      value: generated,
    );

    return generated;
  }

  Future<String> _generateFingerprint() async {
    final platform = DevicePlatform.current();

    final seed = switch (platform) {
      DevicePlatform.android => (await _deviceInfo.androidInfo).id,
      DevicePlatform.ios =>
        (await _deviceInfo.iosInfo).identifierForVendor ?? '',
      DevicePlatform.web || DevicePlatform.unknown => '',
    };

    final random = Random.secure();
    final salt = List.generate(
      16,
      (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0'),
    ).join();

    return '${platform.value}-$seed-$salt'.replaceAll(' ', '');
  }

  String _capitalize(String value) {
    if (value.isEmpty) return value;
    return value[0].toUpperCase() + value.substring(1);
  }
}
