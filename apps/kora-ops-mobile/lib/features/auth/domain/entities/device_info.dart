import 'package:equatable/equatable.dart';

/// Informations affichées sur l'écran "Nouvel appareil détecté". Mockées
/// pour ce sprint (voir document.md § Flux d'authentification) — un vrai
/// fingerprint d'appareil sera branché quand l'API sera disponible.
class DeviceInfo extends Equatable {
  const DeviceInfo({
    required this.deviceModel,
    required this.city,
    required this.attemptLabel,
  });

  /// Ex : "Tecno CAMON"
  final String deviceModel;

  /// Ex : "Cotonou"
  final String city;

  /// Ex : "À l'instant"
  final String attemptLabel;

  @override
  List<Object?> get props => <Object?>[deviceModel, city, attemptLabel];
}
