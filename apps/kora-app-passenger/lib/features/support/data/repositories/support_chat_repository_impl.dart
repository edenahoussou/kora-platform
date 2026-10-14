import 'package:dartz/dartz.dart';

import '../../../../core/error/api_failure_mapper.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/support_chat_configuration.dart';
import '../../domain/repositories/support_chat_repository.dart';
import '../datasources/support_chat_remote_data_source.dart';

class SupportChatRepositoryImpl
    with ApiFailureMapper
    implements SupportChatRepository {
  const SupportChatRepositoryImpl(this._remote);

  final SupportChatRemoteDataSource _remote;

  @override
  Future<Either<Failure, SupportChatConfiguration>> configuration() =>
      attempt(_remote.configuration);
}
