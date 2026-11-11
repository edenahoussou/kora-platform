import 'package:dartz/dartz.dart';

import '../../../../core/error/api_failure_mapper.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/wallet_account.dart';
import '../../domain/entities/wallet_movement.dart';
import '../../domain/entities/wallet_top_up.dart';
import '../../domain/repositories/wallet_repository.dart';
import '../datasources/wallet_remote_data_source.dart';

class WalletRepositoryImpl with ApiFailureMapper implements WalletRepository {
  const WalletRepositoryImpl(this._remote);

  final WalletRemoteDataSource _remote;

  @override
  Future<Either<Failure, WalletAccount>> fetchAccount() =>
      attempt(_remote.fetchAccount);

  @override
  Future<Either<Failure, WalletLedgerPage>> fetchMovements({
    WalletBalanceKind? kind,
    String? cursor,
  }) => attempt(() => _remote.fetchMovements(kind: kind, cursor: cursor));

  @override
  Future<Either<Failure, WalletTopUp>> initiateTopUp({
    required int amount,
    required String method,
    required String provider,
    required String idempotencyKey,
    String? phone,
    String? network,
  }) => attempt(
    () => _remote.initiateTopUp(
      amount: amount,
      method: method,
      provider: provider,
      idempotencyKey: idempotencyKey,
      phone: phone,
      network: network,
    ),
  );

  @override
  Future<Either<Failure, WalletTopUp>> fetchTopUp(String uuid) =>
      attempt(() => _remote.fetchTopUp(uuid));
}
