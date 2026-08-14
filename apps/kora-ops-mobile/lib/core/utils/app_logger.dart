import 'package:logger/logger.dart';

/// Logger structuré unique de l'application — remplace tout `print()`
/// (interdit par le lint `avoid_print`).
abstract final class AppLogger {
  static final Logger _logger = Logger(
    printer: PrettyPrinter(methodCount: 0, colors: false),
  );

  static void d(String message) => _logger.d(message);
  static void i(String message) => _logger.i(message);
  static void w(String message) => _logger.w(message);
  static void e(String message, [Object? error, StackTrace? stackTrace]) =>
      _logger.e(message, error: error, stackTrace: stackTrace);
}
