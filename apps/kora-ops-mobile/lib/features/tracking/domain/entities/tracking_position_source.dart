/// Source de la localisation transmise — miroir de
/// `App\Modules\Tracking\Data\TrackingPositionSource` côté backend
/// (MEZ-100). Les noms d'enum Dart correspondent exactement aux valeurs
/// du contrat JSON (`.name`).
enum TrackingPositionSource {
  gps,
  network,
  fused,
  unknown;

  static TrackingPositionSource fromValue(String? value) =>
      TrackingPositionSource.values.firstWhere(
        (TrackingPositionSource s) => s.name == value,
        orElse: () => unknown,
      );
}
