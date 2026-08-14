import '../../../../core/exceptions/app_exceptions.dart';
import '../../domain/services/barcode_scanner.dart';

/// Implémentation utilisée en développement et sur émulateur/desktop, sans
/// hardware P501. Retourne des données de test réalistes après un court
/// délai simulant la latence du scanner physique.
///
/// Passer `shouldFail: true` (ex. dans un test) pour simuler un échec de
/// scan et vérifier la gestion d'erreur en amont.
class MockBarcodeScanner implements BarcodeScanner {
  MockBarcodeScanner({this.shouldFail = false, this.simulatedDelay = const Duration(milliseconds: 400)});

  final bool shouldFail;
  final Duration simulatedDelay;

  static const List<String> _testCodes = <String>[
    'KORA-TICKET-000123',
    'KORA-TICKET-000124',
    'KORA-TICKET-000125',
  ];

  int _callCount = 0;

  @override
  Future<String> scan() async {
    await Future<void>.delayed(simulatedDelay);
    if (shouldFail) {
      throw const ScannerException('Scan simulé en échec (mock).');
    }
    final String code = _testCodes[_callCount % _testCodes.length];
    _callCount++;
    return code;
  }
}
