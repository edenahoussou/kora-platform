import 'location_fix.dart';
import 'location_permission_status.dart';

/// Abstraction au-dessus du GPS (ticket MEZ-101, « prévoir une
/// abstraction LocationProvider afin que le même parcours fonctionne sur
/// P501 et sur un téléphone Android »).
///
/// Une seule implémentation réelle, [GeolocatorLocationProvider], sert les
/// DEUX modes ([TerminalMode.p501] et [TerminalMode.phone]) : le P501 est
/// lui-même un terminal Android, sa puce GPS passe par le même
/// `FusedLocationProviderClient` que celui d'un téléphone (voir
/// `docs/tracking-location-source.md` côté backend, MEZ-100). C'est
/// précisément le contrat que cette interface capture — le code appelant
/// (`TrackingTaskHandler`) ne sait jamais s'il tourne sur un boîtier
/// dédié ou un téléphone. [MockLocationProvider] sert les tests et le
/// mode `AppConfig.useMockHardware` (même bascule que `BarcodeScanner`/
/// `ThermalPrinter`, voir `injection.dart`).
abstract class LocationProvider {
  Future<LocationPermissionStatus> checkPermission();

  /// Demande la permission foreground puis, seulement si elle est
  /// accordée, la permission background (`ACCESS_BACKGROUND_LOCATION`) —
  /// flux en deux temps recommandé par Android 10+ : présenter les deux
  /// demandes d'un coup fait souvent échouer la seconde silencieusement.
  Future<LocationPermissionStatus> requestPermission();

  Future<bool> isLocationServiceEnabled();

  /// `false` sur Android 12+ si le convoyeur n'a autorisé que la
  /// localisation approximative (« Approximative » dans la boîte de
  /// dialogue système) — un suivi de flotte a besoin de la position
  /// précise. Toujours `true` sur les versions antérieures (pas de
  /// distinction précise/approximative avant Android 12).
  Future<bool> hasPreciseAccuracy();

  /// Flux continu de positions. [intervalSeconds] est une cible, pas une
  /// garantie stricte (dépend de l'OS/du fabricant) ;
  /// [minDistanceMeters] filtre les mesures quasi-identiques à l'arrêt
  /// (bus au dépôt, embarquement) pour ne pas saturer la file locale.
  Stream<LocationFix> watchPosition({
    required int intervalSeconds,
    required double minDistanceMeters,
  });

  /// Émet à chaque changement d'état du service de localisation du
  /// système (activé/désactivé) — utilisé pour envoyer immédiatement un
  /// heartbeat `gpsUnavailable` (voir critère d'acceptation MEZ-101,
  /// « événements de démarrage, arrêt, reconnexion et perte de signal »).
  Stream<bool> watchServiceEnabled();
}
