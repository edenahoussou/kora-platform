import 'package:dartz/dartz.dart';

import '../../../../core/error/api_failure_mapper.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/wallet_account.dart';
import '../../domain/entities/wallet_movement.dart';
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
}
