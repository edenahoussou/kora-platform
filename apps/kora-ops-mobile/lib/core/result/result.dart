import '../errors/failures.dart';

/// Résultat d'une opération métier : soit un succès avec une valeur [T],
/// soit un échec typé [Failure]. Utilisé comme type de retour de tous les
/// UseCases pour que Presentation n'ait jamais à intercepter d'exception.
sealed class Result<T> {
  const Result();

  const factory Result.success(T value) = Success<T>;
  const factory Result.failure(Failure failure) = ResultFailure<T>;

  bool get isSuccess => this is Success<T>;
  bool get isFailure => this is ResultFailure<T>;

  /// Exécute [onSuccess] ou [onFailure] selon le cas, sans if/else répétés
  /// dans les Cubits/Blocs.
  R when<R>({
    required R Function(T value) onSuccess,
    required R Function(Failure failure) onFailure,
  }) {
    final Result<T> self = this;
    if (self is Success<T>) return onSuccess(self.value);
    if (self is ResultFailure<T>) return onFailure(self.failure);
    throw StateError('Result non exhaustif');
  }
}

final class Success<T> extends Result<T> {
  const Success(this.value);
  final T value;
}

final class ResultFailure<T> extends Result<T> {
  const ResultFailure(this.failure);
  final Failure failure;
}
