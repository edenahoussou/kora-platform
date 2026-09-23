import 'dart:io';

import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart' as ph;

import '../../domain/entities/tracking_position_source.dart';
import '../../domain/services/location_fix.dart';
import '../../domain/services/location_permission_status.dart';
import '../../domain/services/location_provider.dart';

/// Implémentation réelle — GPS de l'OS (`FusedLocationProviderClient` sur
/// Android), identique que le terminal soit un P501 ou un téléphone (voir
/// docstring `LocationProvider`). `geolocator` couvre les positions et la
/// permission de premier niveau ; `permission_handler` complète pour la
/// permission arrière-plan (`ACCESS_BACKGROUND_LOCATION`), que geolocator
/// ne redemande jamais séparément sur Android — voir [requestPermission].
class GeolocatorLocationProvider implements LocationProvider {
  /// En dessous de ce seuil de précision (mètres), la mesure est
  /// considérée comme un fix GPS direct plutôt qu'une position dégradée
  /// issue du réseau/Wi-Fi (`FusedLocationProviderClient` ne distingue
  /// pas la source elle-même via l'API cross-platform de geolocator —
  /// heuristique documentée plutôt qu'une vraie lecture de source, voir
  /// `docs/tracking-location-source.md` côté backend).
  static const double _gpsAccuracyThresholdMeters = 20;

  @override
  Future<LocationPermissionStatus> checkPermission() async {
    final LocationPermission permission = await Geolocator.checkPermission();
    return _statusOf(permission, requestBackgroundIfWhileInUse: false);
  }

  @override
  Future<LocationPermissionStatus> requestPermission() async {
    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    return _statusOf(permission, requestBackgroundIfWhileInUse: true);
  }

  /// [requestBackgroundIfWhileInUse] : `true` seulement depuis
  /// [requestPermission] — [checkPermission] ne doit jamais déclencher de
  /// boîte de dialogue système, juste lire l'état courant.
  Future<LocationPermissionStatus> _statusOf(
    LocationPermission permission, {
    required bool requestBackgroundIfWhileInUse,
  }) async {
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.unableToDetermine) {
      return LocationPermissionStatus.denied;
    }
    if (permission == LocationPermission.deniedForever) {
      return LocationPermissionStatus.deniedForever;
    }
    if (permission == LocationPermission.always) {
      return LocationPermissionStatus.grantedAlways;
    }

    // whileInUse : la permission premier plan est accordée, reste
    // l'arrière-plan — non couvert par geolocator sur Android (voir
    // docstring de la classe).
    if (!Platform.isAndroid) {
      return LocationPermissionStatus.grantedWhileInUse;
    }

    final ph.PermissionStatus background = requestBackgroundIfWhileInUse
        ? await ph.Permission.locationAlways.request()
        : await ph.Permission.locationAlways.status;

    return background.isGranted
        ? LocationPermissionStatus.grantedAlways
        : LocationPermissionStatus.grantedWhileInUse;
  }

  @override
  Future<bool> isLocationServiceEnabled() =>
      Geolocator.isLocationServiceEnabled();

  @override
  Future<bool> hasPreciseAccuracy() async {
    try {
      final LocationAccuracyStatus status = await Geolocator.getLocationAccuracy();
      return status == LocationAccuracyStatus.precise;
    } on Object {
      // Plateforme/version qui ne distingue pas précise/approximative
      // (avant Android 12) — pas une restriction dans ce cas.
      return true;
    }
  }

  @override
  Stream<LocationFix> watchPosition({
    required int intervalSeconds,
    required double minDistanceMeters,
  }) {
    final AndroidSettings settings = AndroidSettings(
      accuracy: LocationAccuracy.high,
      distanceFilter: minDistanceMeters.round(),
      intervalDuration: Duration(seconds: intervalSeconds),
      // Pas de forceLocationManager : FusedLocationProviderClient reste
      // le comportement par défaut recommandé.
    );

    return Geolocator.getPositionStream(locationSettings: settings)
        .map(_fixOf);
  }

  @override
  Stream<bool> watchServiceEnabled() => Geolocator.getServiceStatusStream()
      .map((ServiceStatus status) => status == ServiceStatus.enabled);

  LocationFix _fixOf(Position position) {
    final double accuracy = position.accuracy;

    return LocationFix(
      latitude: position.latitude,
      longitude: position.longitude,
      accuracyMeters: accuracy,
      timestamp: position.timestamp,
      source: accuracy <= _gpsAccuracyThresholdMeters
          ? TrackingPositionSource.gps
          : TrackingPositionSource.fused,
      speedKmh: position.speed >= 0 ? position.speed * 3.6 : null,
      headingDegrees: position.heading >= 0 ? position.heading : null,
    );
  }
}
