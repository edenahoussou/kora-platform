import 'package:flutter/widgets.dart';

/// Clé du [Navigator] racine, passée à `GoRouter(navigatorKey: ...)` —
/// permet de pousser un écran depuis du code qui n'a pas de `BuildContext`
/// à disposition (ex. [PhoneCameraBarcodeScanner.scan], appelé depuis
/// `ScanBoardingCubit` sans jamais connaître l'écran d'où il est déclenché,
/// voir docstring de `BarcodeScanner`).
final GlobalKey<NavigatorState> rootNavigatorKey = GlobalKey<NavigatorState>();
