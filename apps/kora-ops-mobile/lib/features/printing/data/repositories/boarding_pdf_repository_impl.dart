import 'dart:typed_data';

import '../../../../core/errors/failures.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../../../core/result/result.dart';
import '../../domain/repositories/boarding_pdf_repository.dart';
import '../datasources/boarding_pdf_remote_datasource.dart';

class BoardingPdfRepositoryImpl implements BoardingPdfRepository {
  BoardingPdfRepositoryImpl(this._remote);

  final BoardingPdfRemoteDataSource _remote;

  @override
  Future<Result<Uint8List>> fetch({
    required String departureUuid,
    required String ticketUuid,
  }) async {
    try {
      final List<int> bytes = await _remote.fetch(
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
      );
      return Result<Uint8List>.success(Uint8List.fromList(bytes));
    } on UnauthorizedException catch (error) {
      return Result<Uint8List>.failure(AuthenticationFailure(error.message));
    } on NetworkException catch (error) {
      return Result<Uint8List>.failure(NetworkFailure(error.message));
    } on ApiException catch (error) {
      return Result<Uint8List>.failure(
        error.statusCode == 422
            ? ValidationFailure(error.message)
            : ServerFailure(error.message),
      );
    } on AppException catch (error) {
      return Result<Uint8List>.failure(ServerFailure(error.message));
    }
  }
}
