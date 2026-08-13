/// Exceptions **typées**, produites par [ErrorInterceptor] à partir de
/// chaque [DioException].
///
/// Pourquoi ce n'est pas juste `on DioException catch (e) { ... }` côté
/// app ? Parce qu'un `DioException` porte des détails de transport (type,
/// code HTTP brut, options de requête...) que la couche `domain` d'une app
/// ne devrait jamais avoir à connaître. [ApiException] est le vocabulaire
/// commun, indépendant de Dio, que toutes les apps KORA peuvent attraper
/// pour décider quoi afficher à l'utilisateur.
///
/// Utilisation typique dans un repository :
/// ```dart
/// try {
///   final response = await dio.get('/users/me');
///   return Right(UserModel.fromJson(response.data));
/// } on DioException catch (e) {
///   final apiException = e.apiException; // voir DioExceptionX
///   return Left(mapToFailure(apiException));
/// }
/// ```
sealed class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode});

  /// Message lisible, déjà pensé pour être affichable à l'utilisateur
  /// (jamais un message technique Dio brut).
  final String message;

  /// Code HTTP renvoyé par le serveur, quand il y en a un (`null` pour les
  /// erreurs qui n'ont jamais atteint le serveur : pas de connexion,
  /// timeout, requête annulée...).
  final int? statusCode;

  @override
  String toString() => '$runtimeType($statusCode): $message';
}

/// Aucune connexion internet, ou connexion perdue en cours de requête.
class NetworkApiException extends ApiException {
  const NetworkApiException([
    super.message = 'Vérifie ta connexion internet et réessaie.',
  ]);
}

/// La requête a dépassé le délai imparti (connexion, envoi ou réception).
class TimeoutApiException extends ApiException {
  const TimeoutApiException([
    super.message = 'Le serveur met trop de temps à répondre.',
  ]);
}

/// 401 — le token est absent, invalide ou expiré.
class UnauthorizedApiException extends ApiException {
  const UnauthorizedApiException([
    super.message = 'Session expirée, merci de te reconnecter.',
  ]) : super(statusCode: 401);
}

/// 403 — authentifié, mais pas autorisé pour cette ressource.
class ForbiddenApiException extends ApiException {
  const ForbiddenApiException([
    super.message = "Tu n'as pas les droits pour effectuer cette action.",
  ]) : super(statusCode: 403);
}

/// 404 — la ressource demandée n'existe pas.
class NotFoundApiException extends ApiException {
  const NotFoundApiException([
    super.message = "La ressource demandée n'existe pas.",
  ]) : super(statusCode: 404);
}

/// 422 (ou 400 selon les endpoints) — les données envoyées sont invalides.
/// [fieldErrors] permet d'afficher l'erreur au bon endroit dans un
/// formulaire (clé = nom du champ, valeur = messages d'erreur pour ce champ).
class ValidationApiException extends ApiException {
  const ValidationApiException(
    super.message, {
    this.fieldErrors = const {},
    int statusCode = 422,
  }) : super(statusCode: statusCode);

  final Map<String, List<String>> fieldErrors;
}

/// 5xx — le serveur a rencontré une erreur.
class ServerApiException extends ApiException {
  const ServerApiException([
    super.message = 'Une erreur serveur est survenue, réessaie plus tard.',
    int? statusCode,
  ]) : super(statusCode: statusCode);
}

/// La requête a été annulée volontairement (ex: navigation avant la fin
/// d'un appel réseau, `CancelToken`).
class CancelledApiException extends ApiException {
  const CancelledApiException([super.message = 'Requête annulée.']);
}

/// Filet de sécurité pour tout ce qui n'est pas prévu explicitement
/// ci-dessus (réponse dans un format inattendu, erreur inconnue...).
class UnknownApiException extends ApiException {
  const UnknownApiException([
    super.message = 'Une erreur inattendue est survenue.',
    int? statusCode,
  ]) : super(statusCode: statusCode);
}
