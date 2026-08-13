import '../errors/api_exception.dart';

/// Enveloppe standardisée du résultat d'un appel API : soit un succès
/// ([ApiSuccess]) portant la donnée déjà désérialisée, soit un échec
/// ([ApiFailure]) portant l'[ApiException] typée correspondante.
///
/// Pensé comme une alternative légère à `Either<Failure, T>` (dartz) pour
/// les apps ou les couches qui ne veulent pas de cette dépendance — les
/// deux approches restent compatibles : une app peut très bien convertir
/// un [ApiResult] en `Either` dans son repository.
///
/// ```dart
/// final result = ApiResult.success(user);
/// final message = switch (result) {
///   ApiSuccess(:final data) => 'Bonjour ${data.name}',
///   ApiFailure(:final exception) => exception.message,
/// };
/// ```
sealed class ApiResult<T> {
  const ApiResult();

  const factory ApiResult.success(T data) = ApiSuccess<T>;
  const factory ApiResult.failure(ApiException exception) = ApiFailure<T>;

  bool get isSuccess => this is ApiSuccess<T>;
  bool get isFailure => this is ApiFailure<T>;

  /// Transforme la donnée en cas de succès, propage l'échec inchangé.
  ApiResult<R> map<R>(R Function(T data) transform) => switch (this) {
    ApiSuccess(:final data) => ApiResult.success(transform(data)),
    ApiFailure(:final exception) => ApiResult.failure(exception),
  };

  /// Réduit le résultat à une seule valeur, quel que soit le cas.
  R fold<R>({
    required R Function(T data) onSuccess,
    required R Function(ApiException exception) onFailure,
  }) => switch (this) {
    ApiSuccess(:final data) => onSuccess(data),
    ApiFailure(:final exception) => onFailure(exception),
  };
}

final class ApiSuccess<T> extends ApiResult<T> {
  const ApiSuccess(this.data);
  final T data;
}

final class ApiFailure<T> extends ApiResult<T> {
  const ApiFailure(this.exception);
  final ApiException exception;
}
