/// Interface d'accès au scanner de code-barres/QR du terminal.
///
/// Deux implémentations : [MockBarcodeScanner] (données de test, utilisée
/// par défaut) et [P501BarcodeScanner] (SDK natif via MethodChannel) — voir
/// document.md § Adaptateurs P501 pour le mécanisme de switch.
abstract interface class BarcodeScanner {
  /// Déclenche un scan et retourne le contenu décodé.
  ///
  /// Lève une [ScannerException] (`core/exceptions`) si le scan échoue, est
  /// annulé par l'utilisateur, ou si le SDK matériel renvoie une erreur.
  Future<String> scan();
}
