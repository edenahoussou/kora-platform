import 'package:flutter/material.dart';

/// Points de rupture utilisés pour adapter les écrans KORA OPS aux
/// téléphones, terminaux P501 (souvent proches d'un petit téléphone) et
/// tablettes utilisées en agence.
enum DeviceType { mobile, tablet, desktop }

/// Outils de responsivité de l'application.
///
/// Complète `flutter_screenutil` (mise à l'échelle proportionnelle des
/// tailles/polices via `.w`, `.h`, `.sp`, `.r` — voir [ScreenUtilInit] dans
/// `app.dart`) par des helpers de layout adaptatif (breakpoints, colonnes,
/// valeur selon le type d'appareil) qui ne dépendent d'aucun package tiers.
abstract final class Responsive {
  static const double mobileBreakpoint = 600;
  static const double tabletBreakpoint = 1024;

  static DeviceType deviceTypeOf(BuildContext context) {
    final double width = MediaQuery.sizeOf(context).width;
    if (width >= tabletBreakpoint) return DeviceType.desktop;
    if (width >= mobileBreakpoint) return DeviceType.tablet;
    return DeviceType.mobile;
  }

  static bool isMobile(BuildContext context) => deviceTypeOf(context) == DeviceType.mobile;

  static bool isTablet(BuildContext context) => deviceTypeOf(context) == DeviceType.tablet;

  static bool isDesktop(BuildContext context) => deviceTypeOf(context) == DeviceType.desktop;

  /// Retourne `mobile`, `tablet` ou `desktop` selon la largeur d'écran
  /// courante — utile pour choisir une valeur (nombre de colonnes, padding,
  /// taille de police) sans dupliquer de logique de breakpoint dans chaque
  /// widget.
  static T value<T>(
    BuildContext context, {
    required T mobile,
    T? tablet,
    T? desktop,
  }) {
    switch (deviceTypeOf(context)) {
      case DeviceType.desktop:
        return desktop ?? tablet ?? mobile;
      case DeviceType.tablet:
        return tablet ?? mobile;
      case DeviceType.mobile:
        return mobile;
    }
  }

  /// Nombre de colonnes suggéré pour une grille (listes de manifestes,
  /// cartes de tournée) selon la largeur disponible.
  static int gridColumns(BuildContext context) => value<int>(
        context,
        mobile: 1,
        tablet: 2,
        desktop: 3,
      );
}

/// Wrapper pratique pour lire `MediaQuery` sans le répéter dans chaque
/// widget (padding sûr, orientation).
extension ResponsiveContextX on BuildContext {
  DeviceType get deviceType => Responsive.deviceTypeOf(this);
  bool get isMobileLayout => Responsive.isMobile(this);
  bool get isTabletLayout => Responsive.isTablet(this);
  bool get isDesktopLayout => Responsive.isDesktop(this);
  EdgeInsets get safeAreaPadding => MediaQuery.paddingOf(this);
  Orientation get orientation => MediaQuery.orientationOf(this);
}
