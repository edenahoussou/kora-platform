import '../../../../core/errors/failures.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../../../core/result/result.dart';
import '../services/barcode_scanner.dart';

class ScanBarcode {
  const ScanBarcode(this._scanner);

  final BarcodeScanner _scanner;

  Future<Result<String>> call() async {
    try {
      final String code = await _scanner.scan();
      return Result<String>.success(code);
    } on ScannerException catch (error) {
      return Result<String>.failure(HardwareFailure(error.message));
    }
  }
}
