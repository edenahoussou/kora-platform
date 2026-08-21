import 'dart:math';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../../core/constants/storage_keys.dart';
import '../../domain/entities/auth_device.dart';
import '../../domain/entities/device_platform.dart';

/// Couche DATA : décrit l'appareil courant pour l'API.
///
/// `register/complete` et `login` exigent un `device_fingerprint` **stable**
/// — c'est lui qui dit au serveur « tu me connais déjà », et donc qui
/// évite de redemander un code à chaque connexion.
abstract class DeviceInfoDataSource {
  /// Nom, plateforme et empreinte de l'appareil courant.
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

  /// Nom lisible, celui que l'utilisateur reconnaîtra dans la liste de ses
  /// appareils de confiance.
  Future<String> _readName(DevicePlatform platform) async {
    switch (platform) {
      case DevicePlatform.android:
        final info = await _deviceInfo.androidInfo;
        // `model` seul donne « SM-S921B » ; avec la marque on obtient
        // « Samsung SM-S921B », déjà plus parlant.
        return '${_capitalize(info.brand)} ${info.model}'.trim();
      case DevicePlatform.ios:
        final info = await _deviceInfo.iosInfo;
        // `name` est le nom donné par l'utilisateur (« iPhone d'Aïcha ») ;
        // il n'est plus lisible sans autorisation depuis iOS 16, d'où le
        // repli sur le modèle.
        return info.name.isEmpty ? info.utsname.machine : info.name;
      case DevicePlatform.web:
      case DevicePlatform.unknown:
        return 'Appareil KORA';
    }
  }

  /// Empreinte stable de l'appareil.
  ///
  /// Rangée dans le stockage sécurisé dès la première génération, et
  /// relue ensuite : les identifiants système ne sont pas fiables comme
  /// clé unique — l'`androidId` change à une réinitialisation d'usine et
  /// l'`identifierForVendor` iOS est perdu si toutes les apps de
  /// l'éditeur sont désinstallées. Ce qui compte pour le serveur, c'est
  /// qu'elle ne bouge pas entre deux lancements ; qu'elle change après une
  /// réinstallation est acceptable — l'utilisateur revérifiera un code.
  Future<String> _fingerprint() async {
    final stored = await _storage.read(key: StorageKeys.deviceFingerprint);
    if (stored != null && stored.isNotEmpty) return stored;

    final generated = await _generateFingerprint();
    await _storage.write(key: StorageKeys.deviceFingerprint, value: generated);

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

    // Le hasard complète l'identifiant système, et le remplace quand il
    // manque : deux installations sur le même appareil doivent rester
    // distinguables, et l'empreinte ne doit pas être devinable.
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
