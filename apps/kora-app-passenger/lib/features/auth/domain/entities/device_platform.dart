import 'dart:io' show Platform;

/// Plateforme déclarée au serveur lors de l'enregistrement d'un appareil.
enum DevicePlatform {
  android('android'),
  ios('ios'),
  web('web'),
  unknown('unknown');

  const DevicePlatform(this.value);

  final String value;

  /// Plateforme réellement en cours d'exécution.
  ///
  /// `unknown` plutôt qu'une erreur sur les cibles desktop : l'API accepte
  /// cette valeur, et un développeur qui lance l'app sur Windows doit
  /// pouvoir se connecter au staging.
  static DevicePlatform current() {
    if (Platform.isAndroid) return DevicePlatform.android;
    if (Platform.isIOS) return DevicePlatform.ios;
    return DevicePlatform.unknown;
  }

  static DevicePlatform fromValue(String? value) =>
      DevicePlatform.values.firstWhere(
        (platform) => platform.value == value,
        orElse: () => DevicePlatform.unknown,
      );
}
