import 'dart:async';

import '../../domain/entities/tracking_position_source.dart';
import '../../domain/services/location_fix.dart';
import '../../domain/services/location_permission_status.dart';
import '../../domain/services/location_provider.dart';

/// Implémentation Mock — utilisée quand `AppConfig.useMockHardware` est
/// vrai (dev/staging sans matériel, même bascule que `MockBarcodeScanner`/
/// `MockThermalPrinter`) et dans les tests. Simule un bus qui avance en
/// ligne droite depuis [startLatitude]/[startLongitude], un point toutes
/// les [intervalSeconds] secondes.
///
/// Les champs contrôlables ([permissionStatus], [serviceEnabled],
/// [preciseAccuracy]) permettent aux tests de simuler chaque état affiché
/// au convoyeur (GPS désactivé, permission refusée, précision
/// insuffisante — voir critères d'acceptation MEZ-101) sans dépendance à
/// un vrai GPS ni au P501.
class MockLocationProvider implements LocationProvider {
  MockLocationProvider({
    this.startLatitude = 6.3654,
    this.startLongitude = 2.4183,
    LocationPermissionStatus? initialPermission,
  }) : permissionStatus =
            initialPermission ?? LocationPermissionStatus.grantedAlways;

  final double startLatitude;
  final double startLongitude;

  /// Modifiable directement par les tests — [checkPermission] et
  /// [requestPermission] (qui « accorde » toujours en Mock) lisent cette
  /// valeur.
  LocationPermissionStatus permissionStatus;
  bool serviceEnabled = true;
  bool preciseAccuracy = true;

  final StreamController<bool> _serviceEnabledController =
      StreamController<bool>.broadcast();

  int _step = 0;

  @override
  Future<LocationPermissionStatus> checkPermission() async => permissionStatus;

  @override
  Future<LocationPermissionStatus> requestPermission() async {
    if (permissionStatus == LocationPermissionStatus.denied) {
      permissionStatus = LocationPermissionStatus.grantedAlways;
    }
    return permissionStatus;
  }

  @override
  Future<bool> isLocationServiceEnabled() async => serviceEnabled;

  @override
  Future<bool> hasPreciseAccuracy() async => preciseAccuracy;

  /// Change [serviceEnabled] et le notifie sur [watchServiceEnabled] —
  /// simule une désactivation/réactivation du GPS pendant un test.
  void setServiceEnabled(bool value) {
    serviceEnabled = value;
    _serviceEnabledController.add(value);
  }

  @override
  Stream<LocationFix> watchPosition({
    required int intervalSeconds,
    required double minDistanceMeters,
  }) {
    return Stream<LocationFix>.periodic(
      Duration(seconds: intervalSeconds),
      (int _) {
        _step++;
        // ~0,001° ≈ 100 m par pas — trajectoire simple mais réaliste pour
        // vérifier l'ordre des séquences côté serveur.
        return LocationFix(
          latitude: startLatitude + (_step * 0.001),
          longitude: startLongitude + (_step * 0.001),
          accuracyMeters: preciseAccuracy ? 8 : 120,
          timestamp: DateTime.now(),
          source: TrackingPositionSource.gps,
          speedKmh: 45,
          headingDegrees: 90,
        );
      },
    );
  }

  @override
  Stream<bool> watchServiceEnabled() => _serviceEnabledController.stream;

  Future<void> dispose() => _serviceEnabledController.close();
}
