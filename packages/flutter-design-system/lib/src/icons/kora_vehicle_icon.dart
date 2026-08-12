import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Les types de véhicules disponibles dans KORA Ride, repris du prototype
/// (`docs/maquettes/kora-app-passenger/js/app.js`, fonction `mobilityPictogram`).
enum KoraVehicleType { moto, keke, bus, xl, standard, comfort }

/// Icône vectorielle d'un véhicule, chargée depuis un vrai fichier `.svg`
/// (`assets/icons/vehicles/`) via [flutter_svg].
///
/// **Ces SVG sont une copie exacte des tracés du prototype HTML**
/// (`<path>`, `<circle>`, `<rect>` identiques à ceux de
/// `js/app.js` → fonction `mobilityPictogram`). Le rendu est donc
/// pixel-identique à l'original, contrairement à un dessin "à la main".
///
/// Les traits sont blancs et l'étoile de la variante "confort" est dorée
/// **en dur dans le fichier SVG** (comme dans le CSS original :
/// `.ride-vehicle-icon svg { stroke: white }` et `.picto-star { fill: gold }`).
/// C'est volontaire : ces icônes sont toujours affichées sur un fond coloré
/// (navy ou digital), donc le blanc fixe est correct dans 100% des cas
/// d'usage du design original. Si un jour tu as besoin d'une icône teintée
/// différemment (ex: sur fond blanc), passe un [colorFilter] explicite.
///
/// Exemple d'utilisation :
/// ```dart
/// KoraVehicleIcon(type: KoraVehicleType.moto, size: 40)
/// ```
class KoraVehicleIcon extends StatelessWidget {
  const KoraVehicleIcon({
    super.key,
    required this.type,
    this.size = 32,
    this.colorFilter,
  });

  final KoraVehicleType type;
  final double size;

  /// Teinte optionnelle. Laisse à `null` pour garder les couleurs fidèles
  /// au prototype (blanc + étoile dorée pour "confort").
  final ColorFilter? colorFilter;

  static const Map<KoraVehicleType, String> _assetPaths = {
    KoraVehicleType.moto: 'assets/icons/vehicles/vehicle_moto.svg',
    KoraVehicleType.keke: 'assets/icons/vehicles/vehicle_keke.svg',
    KoraVehicleType.bus: 'assets/icons/vehicles/vehicle_bus.svg',
    KoraVehicleType.xl: 'assets/icons/vehicles/vehicle_xl.svg',
    KoraVehicleType.standard: 'assets/icons/vehicles/vehicle_standard.svg',
    KoraVehicleType.comfort: 'assets/icons/vehicles/vehicle_comfort.svg',
  };

  @override
  Widget build(BuildContext context) {
    return SvgPicture.asset(
      _assetPaths[type]!,
      // Voir la note dans kora_nav_icon.dart : asset déclaré dans le
      // pubspec de ce package, pas dans celui de l'app consommatrice.
      package: 'flutter_design_system',
      width: size,
      // Le viewBox original est 64x40 (ratio ~1.6), on garde ce ratio.
      height: size * (40 / 64),
      colorFilter: colorFilter,
    );
  }
}
