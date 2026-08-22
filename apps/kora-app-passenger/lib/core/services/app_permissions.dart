import 'package:permission_handler/permission_handler.dart';

/// Ce que le système a répondu pour une autorisation.
enum AppPermissionStatus {
  granted,
  denied,

  /// Refus définitif : seul un passage par les réglages du téléphone peut
  /// le lever, redemander ici n'afficherait plus rien.
  permanentlyDenied,
}

/// Accès aux autorisations du téléphone, derrière une interface à nous.
///
/// L'écran des autorisations ne dépend donc pas de `permission_handler` :
/// ses tests fournissent une implémentation de mémoire, sans quoi il
/// faudrait un vrai système d'exploitation pour les faire tourner.
abstract class AppPermissions {
  Future<AppPermissionStatus> requestLocation();

  Future<AppPermissionStatus> requestNotifications();
}

class AppPermissionsImpl implements AppPermissions {
  const AppPermissionsImpl();

  @override
  Future<AppPermissionStatus> requestLocation() =>
      _request(Permission.locationWhenInUse);

  @override
  Future<AppPermissionStatus> requestNotifications() =>
      _request(Permission.notification);

  Future<AppPermissionStatus> _request(Permission permission) async {
    final status = await permission.request();

    // `limited` (iOS) et `provisional` (notifications discrètes d'iOS)
    // laissent passer l'essentiel : l'écran les traite comme un accord.
    // `restricted`, en revanche, vient d'un contrôle parental et ne se
    // rattrape pas en redemandant — il rejoint le refus définitif.
    return switch (status) {
      PermissionStatus.granted ||
      PermissionStatus.limited ||
      PermissionStatus.provisional => AppPermissionStatus.granted,
      PermissionStatus.permanentlyDenied ||
      PermissionStatus.restricted => AppPermissionStatus.permanentlyDenied,
      PermissionStatus.denied => AppPermissionStatus.denied,
    };
  }
}
