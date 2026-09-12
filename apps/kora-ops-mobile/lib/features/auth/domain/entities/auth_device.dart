import 'package:equatable/equatable.dart';

import 'device_platform.dart';

/// Un appareil de confiance, tel que le renvoie l'API. C'est ce qui permet
/// de sauter l'OTP : le serveur reconnaît l'empreinte et n'exige plus de
/// code.
class AuthDevice extends Equatable {
  const AuthDevice({
    required this.uuid,
    required this.name,
    required this.platform,
    required this.status,
    this.trustedAt,
    this.lastSeenAt,
  });

  final String uuid;
  final String name;
  final DevicePlatform platform;
  final String status;
  final DateTime? trustedAt;
  final DateTime? lastSeenAt;

  @override
  List<Object?> get props =>
      <Object?>[uuid, name, platform, status, trustedAt, lastSeenAt];
}

/// L'appareil courant, tel qu'on le déclare au serveur à chaque connexion.
class DeviceRegistration extends Equatable {
  const DeviceRegistration({
    required this.name,
    required this.platform,
    required this.fingerprint,
    this.pushToken,
  });

  /// Nom lisible — « Samsung SM-S921B » — affiché dans la liste des
  /// appareils de confiance du compte.
  final String name;

  final DevicePlatform platform;

  /// Identifiant stable de l'appareil, qui doit rester le même d'un
  /// lancement à l'autre — c'est lui que le serveur compare pour décider
  /// s'il exige un OTP.
  final String fingerprint;

  final String? pushToken;

  Map<String, dynamic> toJson() => <String, dynamic>{
        'device_name': name,
        'platform': platform.value,
        'device_fingerprint': fingerprint,
        if (pushToken != null) 'push_token': pushToken,
      };

  @override
  List<Object?> get props => <Object?>[name, platform, fingerprint, pushToken];
}
