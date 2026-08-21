import 'package:dio/dio.dart';

import 'api_exception.dart';

/// Traduit un [DioException] (erreur de transport Dio) en [ApiException]
/// (erreur métier, indépendante de Dio).
///
/// Le corps de réponse attendu pour les erreurs serveur suit le format de
/// l'API KORA (Laravel) :
/// ```json
/// { "message": "The given data was invalid.", "errors": { "email": ["..."] } }
/// ```
/// mais le mapper reste tolérant : un corps dans un autre format retombe
/// simplement sur le message par défaut de l'exception correspondante,
/// sans jamais lever d'erreur secondaire pendant le parsing.
abstract final class ApiExceptionMapper {
  static ApiException fromDioException(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return const TimeoutApiException();

      case DioExceptionType.connectionError:
        return const NetworkApiException();

      case DioExceptionType.cancel:
        return const CancelledApiException();

      case DioExceptionType.badCertificate:
        return const NetworkApiException(
          'La connexion sécurisée au serveur a échoué.',
        );

      case DioExceptionType.badResponse:
        return _fromStatusCode(error);

      case DioExceptionType.unknown:
        // Sous Dio, une erreur socket (pas d'internet) sans réponse HTTP
        // atterrit souvent ici plutôt que dans `connectionError` selon la
        // plateforme — on la traite donc comme une erreur réseau plutôt
        // que de la laisser tomber dans le filet générique.
        return const NetworkApiException();

      case DioExceptionType.transformTimeout:
        return const UnknownApiException(
          'La réponse du serveur est dans un format inattendu.',
        );
    }
  }

  static ApiException _fromStatusCode(DioException error) {
    final statusCode = error.response?.statusCode;
    final message = _extractMessage(error.response?.data);

    switch (statusCode) {
      case 401:
        return UnauthorizedApiException(
          message ?? const UnauthorizedApiException().message,
        );
      case 403:
        return ForbiddenApiException(
          message ?? const ForbiddenApiException().message,
        );
      case 404:
        return NotFoundApiException(
          message ?? const NotFoundApiException().message,
        );
      case 422:
        return ValidationApiException(
          message ?? 'Les données envoyées sont invalides.',
          fieldErrors: _extractFieldErrors(error.response?.data),
        );
      case 423:
        // « Locked » : le compte est bloqué après trop d'erreurs de PIN et
        // se rouvre par un OTP. Sans ce cas, il retombait dans le filet
        // générique et l'app ne pouvait pas proposer le déverrouillage.
        return LockedApiException(
          message ?? const LockedApiException().message,
        );
      default:
        if (statusCode != null && statusCode >= 500) {
          return ServerApiException(
            message ?? const ServerApiException().message,
            statusCode,
          );
        }
        return UnknownApiException(
          message ?? const UnknownApiException().message,
          statusCode,
        );
    }
  }

  static String? _extractMessage(Object? responseData) {
    if (responseData is! Map) return null;

    final message = responseData['message'];
    if (message is String && message.isNotEmpty) return message;

    // Seconde forme, celle des erreurs métier de l'API KORA :
    // `{ "error": { "code": "account_locked", "message": "…" } }`.
    // Sans ce cas, ces réponses retombaient sur le message par défaut de
    // l'exception et le texte du serveur était perdu.
    final error = responseData['error'];
    if (error is Map) {
      final nested = error['message'];
      if (nested is String && nested.isNotEmpty) return nested;
    }

    return null;
  }

  static Map<String, List<String>> _extractFieldErrors(Object? responseData) {
    if (responseData is! Map) return const {};
    final errors = responseData['errors'];
    if (errors is! Map) return const {};

    return errors.map((key, value) {
      final messages = value is List
          ? value.map((e) => e.toString()).toList()
          : <String>[value.toString()];
      return MapEntry(key.toString(), messages);
    });
  }
}
