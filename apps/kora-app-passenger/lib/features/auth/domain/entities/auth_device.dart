import 'package:equatable/equatable.dart';

import 'device_platform.dart';

/// Un appareil de confiance, tel que le renvoie l'API.
///
/// C'est ce qui permet de sauter l'OTP : le serveur reconnaît l'empreinte
/// et n'exige plus de code. Un appareil révoqué redemandera un OTP à la
/// prochaine connexion.
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

  /// `trusted` dans la doc ; laissé en `String` faute d'une liste fermée
  /// documentée — inventer un enum ici masquerait toute valeur inattendue.
  final String status;

  final DateTime? trustedAt;
  final DateTime? lastSeenAt;

  @override
  List<Object?> get props => [
    uuid,
    name,
    platform,
    status,
    trustedAt,
    lastSeenAt,
  ];
}

/// L'appareil courant, tel qu'on le déclare au serveur.
class DeviceRegistration extends Equatable {
  const DeviceRegistration({
    required this.name,
    required this.platform,
    required this.fingerprint,
    this.pushToken,
  });

  /// Nom lisible — « iPhone 15 Pro », « Samsung Galaxy S24 ». C'est ce que
  /// l'utilisateur verra dans la liste de ses appareils de confiance.
  final String name;

  final DevicePlatform platform;

  /// Identifiant stable de l'appareil. Le serveur s'en sert pour décider
  /// s'il exige un OTP : il doit rester le même d'un lancement à l'autre.
  final String fingerprint;

  /// Jeton de notifications, quand il existe.
  final String? pushToken;

  Map<String, dynamic> toJson() => {
    'device_name': name,
    'platform': platform.value,
    'device_fingerprint': fingerprint,
    if (pushToken != null) 'push_token': pushToken,
  };

  @override
  List<Object?> get props => [name, platform, fingerprint, pushToken];
}
