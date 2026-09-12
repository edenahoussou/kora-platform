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
