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

/// Nature d'une panne d'imprimante — permet à la présentation d'afficher un
/// message compréhensible et d'adapter l'action proposée (recharger le
/// papier, vérifier la connexion…) sans reparser une chaîne d'erreur.
enum PrinterFailureKind {
  /// Imprimante absente, déconnectée, ou canal natif P501 non disponible.
  notConnected,

  /// Plus de papier dans le rouleau.
  outOfPaper,

  /// Tête d'impression en surchauffe.
  overheated,

  /// Toute autre erreur du SDK / du matériel.
  unknown,
}

extension PrinterFailureKindX on PrinterFailureKind {
  /// Message destiné au convoyeur. [fallback] n'est utilisé que pour
  /// [PrinterFailureKind.unknown] (le message du SDK est alors le seul
  /// indice disponible).
  String userMessage([String? fallback]) => switch (this) {
        PrinterFailureKind.notConnected =>
          'Imprimante introuvable ou déconnectée. Vérifiez le terminal '
              'puis réessayez.',
        PrinterFailureKind.outOfPaper =>
          'Plus de papier dans l\'imprimante. Rechargez le rouleau puis '
              'reprenez l\'impression.',
        PrinterFailureKind.overheated =>
          'L\'imprimante est en surchauffe. Patientez quelques instants '
              'puis reprenez l\'impression.',
        PrinterFailureKind.unknown =>
          (fallback == null || fallback.trim().isEmpty)
              ? 'Erreur de l\'imprimante. Réessayez.'
              : fallback,
      };
}

/// L'impression a échoué (papier absent, imprimante déconnectée, SDK P501
/// en erreur…). [kind] précise la panne quand elle est identifiable.
class PrinterException extends AppException {
  const PrinterException(
    [super.message = 'Erreur de l\'imprimante.',
    this.kind = PrinterFailureKind.unknown]);

  final PrinterFailureKind kind;

  /// Message affichable tel quel au convoyeur.
  String get userMessage => kind.userMessage(message);
}
