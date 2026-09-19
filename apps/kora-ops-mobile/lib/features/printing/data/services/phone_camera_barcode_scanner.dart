import 'package:flutter/material.dart';

import '../../../../app/router/root_navigator_key.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../domain/services/barcode_scanner.dart';
import '../../presentation/pages/camera_scan_page.dart';

/// Scan par caméra téléphone — alternative au P501 en mode téléphone (voir
/// ticket "Supporter le scan par caméra téléphone en alternative au
/// P501"). Implémente le même contrat [BarcodeScanner] que
/// [P501BarcodeScanner] : le code appelant (`ScanBoardingCubit`,
/// `BoardingRepository`) ne sait rien de la source matérielle, seul
/// `injection.dart` choisit l'un ou l'autre selon
/// `AppConfig.terminalMode`.
///
/// Contrairement au P501, dont le SDK déclenche le scan et renvoie
/// directement un résultat, la caméra est intrinsèquement un flux vidéo
/// continu : ce scanner pousse donc un écran plein écran ([CameraScanPage])
/// via [rootNavigatorKey] (pas de `BuildContext` disponible à cet appel)
/// et attend sa fermeture — le point d'appel unique (`scan()`) reste
/// identique à celui du P501.
class PhoneCameraBarcodeScanner implements BarcodeScanner {
  PhoneCameraBarcodeScanner({GlobalKey<NavigatorState>? navigatorKey})
      : _navigatorKey = navigatorKey ?? rootNavigatorKey;

  final GlobalKey<NavigatorState> _navigatorKey;

  @override
  Future<String> scan() async {
    final NavigatorState? navigator = _navigatorKey.currentState;
    if (navigator == null) {
      throw const ScannerException(
        'Caméra indisponible : aucun écran actif.',
      );
    }

    final String? code = await navigator.push<String>(
      MaterialPageRoute<String>(builder: (_) => const CameraScanPage()),
    );

    if (code == null || code.isEmpty) {
      throw const ScannerException('Scan annulé.');
    }

    return code;
  }
}
