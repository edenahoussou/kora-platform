/// Exceptions levées par les couches Data (datasources, client réseau) et
/// converties en [Failure] par les Repository avant d'atteindre le Domain.
sealed class AppException implements Exception {
  const AppException(this.message);

  final String message;

  @override
  String toString() => message;
}

class ApiException extends AppException {
  const ApiException(super.message, {this.statusCode});

  final int? statusCode;
}

class NetworkException extends AppException {
  const NetworkException([super.message = 'Pas de connexion réseau.']);
}

class UnauthorizedException extends AppException {
  const UnauthorizedException(
      [super.message = 'Session expirée ou non authentifiée.']);
}

class ServerException extends AppException {
  const ServerException([super.message = 'Erreur serveur.']);
}

class CacheException extends AppException {
  const CacheException([super.message = 'Erreur de stockage local.']);
}

/// Le scan a échoué, a été annulé, ou le SDK du scanner P501 a renvoyé une
/// erreur.
class ScannerException extends AppException {
  const ScannerException([super.message = 'Erreur du scanner.']);
}

/// L'impression a échoué (papier absent, imprimante déconnectée, SDK P501
/// en erreur…).
class PrinterException extends AppException {
  const PrinterException([super.message = 'Erreur de l\'imprimante.']);
}
