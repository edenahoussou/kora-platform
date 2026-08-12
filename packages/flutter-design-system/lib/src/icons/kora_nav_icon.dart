import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Les 5 onglets de la barre de navigation, repris du prototype
/// (`KORA/design/js/app.js`, fonction `nav`).
enum KoraNavIconType { home, tickets, parcel, wallet, profile }

/// Icône de navigation basée sur un `.svg` (`assets/icons/nav/`), recréée
/// à la main pour reproduire fidèlement les pictogrammes CSS du prototype
/// (`.nav-home`, `.nav-ticket`, `.nav-parcel`, `.nav-wallet`,
/// `.nav-profile` — dessinés en CSS pur dans l'original, donc pas de vrai
/// SVG source à copier ; on les a redessinés en conservant exactement la
/// même silhouette).
///
/// Contrairement aux icônes véhicules, ces SVG utilisent une couleur
/// "neutre" (`#000000`) dans le fichier : c'est fait exprès pour qu'on
/// puisse les teinter dynamiquement avec [color] (actif = doré, inactif =
/// gris) via un `ColorFilter`, exactement comme `currentColor` en CSS.
///
/// Exemple d'utilisation :
/// ```dart
/// KoraNavIcon(type: KoraNavIconType.home, color: AppColors.gold, size: 20)
/// ```
class KoraNavIcon extends StatelessWidget {
  const KoraNavIcon({
    super.key,
    required this.type,
    required this.color,
    this.size = 20,
  });

  final KoraNavIconType type;
  final Color color;
  final double size;

  static const Map<KoraNavIconType, String> _assetPaths = {
    KoraNavIconType.home: 'assets/icons/nav/nav_home.svg',
    KoraNavIconType.tickets: 'assets/icons/nav/nav_tickets.svg',
    KoraNavIconType.parcel: 'assets/icons/nav/nav_parcel.svg',
    KoraNavIconType.wallet: 'assets/icons/nav/nav_wallet.svg',
    KoraNavIconType.profile: 'assets/icons/nav/nav_profile.svg',
  };

  @override
  Widget build(BuildContext context) {
    return SvgPicture.asset(
      _assetPaths[type]!,
      // Cet asset est déclaré dans le pubspec.yaml de CE package
      // (flutter_design_system), pas dans celui de l'app consommatrice :
      // il faut donc explicitement dire à Flutter dans quel package le
      // chercher (sinon il chercherait dans le bundle de l'app).
      package: 'flutter_design_system',
      width: size,
      height: size,
      colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
    );
  }
}
