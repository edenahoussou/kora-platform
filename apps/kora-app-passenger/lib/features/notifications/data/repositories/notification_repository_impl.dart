import 'package:dartz/dartz.dart';

import '../../../../core/error/api_failure_mapper.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/notification_feed.dart';
import '../../domain/repositories/notification_repository.dart';
import '../datasources/notification_remote_data_source.dart';

class NotificationRepositoryImpl
    with ApiFailureMapper
    implements NotificationRepository {
  const NotificationRepositoryImpl(this._remote);

  final NotificationRemoteDataSource _remote;

  @override
  Future<Either<Failure, NotificationFeed>> fetch({
    int page = 1,
    int perPage = 20,
  }) => attempt(() => _remote.fetch(page: page, perPage: perPage));

  @override
  Future<Either<Failure, Unit>> markRead(String uuid) => attempt(() async {
    await _remote.markRead(uuid);
    return unit;
  });

  @override
  Future<Either<Failure, Unit>> markAllRead() => attempt(() async {
    await _remote.markAllRead();
    return unit;
  });
}
