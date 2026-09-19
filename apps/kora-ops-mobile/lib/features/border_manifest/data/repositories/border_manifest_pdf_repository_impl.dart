import 'dart:typed_data';

import '../../../../core/errors/failures.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../../../core/result/result.dart';
import '../../domain/repositories/border_manifest_pdf_repository.dart';
import '../datasources/border_manifest_pdf_remote_datasource.dart';

class BorderManifestPdfRepositoryImpl implements BorderManifestPdfRepository {
  BorderManifestPdfRepositoryImpl(this._remote);

  final BorderManifestPdfRemoteDataSource _remote;

  @override
  Future<Result<Uint8List>> fetch({required String departureUuid}) async {
    try {
      final List<int> bytes = await _remote.fetch(departureUuid: departureUuid);
      return Result<Uint8List>.success(Uint8List.fromList(bytes));
    } on BorderManifestIncompleteException catch (error) {
      final String details =
          error.details.isEmpty ? '' : '\n${error.details.join('\n')}';
      return Result<Uint8List>.failure(
        ValidationFailure('${error.message}$details'),
      );
    } on UnauthorizedException catch (error) {
      return Result<Uint8List>.failure(AuthenticationFailure(error.message));
    } on NetworkException {
      return const Result<Uint8List>.failure(NetworkFailure(
        'Connexion requise pour générer le manifeste PDF. '
        'Réessayez une fois en ligne.',
      ));
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
