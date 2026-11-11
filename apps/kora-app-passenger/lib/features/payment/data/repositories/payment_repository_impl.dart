import 'package:dartz/dartz.dart';

import '../../../../core/error/api_failure_mapper.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/payment_attempt.dart';
import '../../domain/entities/payment_catalog.dart';
import '../../domain/repositories/payment_repository.dart';
import '../datasources/payment_remote_data_source.dart';

/// Couche DATA : implémentation du contrat [PaymentRepository].
///
/// Comme les autres dépôts, il ne fait que traduire les erreurs
/// techniques en [Failure] métier, via le mixin partagé — un 401 sur le
/// catalogue doit produire exactement le même `AuthFailure` que sur le
/// reste du tunnel, sinon deux écrans réagissent différemment à une même
/// session expirée.
class PaymentRepositoryImpl with ApiFailureMapper implements PaymentRepository {
  const PaymentRepositoryImpl(this._remote);

  final PaymentRemoteDataSource _remote;

  @override
  Future<Either<Failure, PaymentCatalog>> catalog() => attempt(_remote.catalog);

  @override
  Future<Either<Failure, PaymentAttempt>> initiate({
    required String reservationId,
    required String method,
    required String provider,
    required String idempotencyKey,
    String? network,
    String? phone,
    int? walletAmount,
    int? pointsAmount,
  }) {
    return attempt(
      () => _remote.initiate(
        reservationId: reservationId,
        method: method,
        provider: provider,
        idempotencyKey: idempotencyKey,
        network: network,
        phone: phone,
      ),
    );
  }

  @override
  Future<Either<Failure, PaymentAttempt>> status(String paymentId) =>
      attempt(() => _remote.status(paymentId));

  @override
  Future<Either<Failure, PaymentAttempt>> verify(
    String paymentId, {
    String? transactionId,
  }) => attempt(() => _remote.verify(paymentId, transactionId: transactionId));
}
