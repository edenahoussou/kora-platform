import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show
        ApiException,
        ForbiddenApiException,
        NetworkApiException,
        TimeoutApiException,
        ServerApiException,
        UnauthorizedApiException,
        ValidationApiException;

import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../domain/entities/terminal.dart';
import '../../domain/entities/terminal_activity_type.dart';
import '../../domain/repositories/terminal_repository.dart';
import '../datasources/terminal_local_datasource.dart';
import '../datasources/terminal_remote_datasource.dart';

/// Couche DATA : assemble le réseau et le stockage local derrière le
/// contrat du domaine — voir docstring de `TerminalRepository`.
class TerminalRepositoryImpl implements TerminalRepository {
  TerminalRepositoryImpl(
    this._remote,
    this._local, {
    DateTime Function()? now,
  }) : _now = now ?? DateTime.now;

  final TerminalRemoteDataSource _remote;
  final TerminalLocalDataSource _local;
  final DateTime Function() _now;

  @override
  Future<Terminal?> cachedTerminal() => _local.readSnapshot();

  @override
  Future<Result<Terminal>> handshake(String serialNumber) async {
    try {
      final Terminal terminal = await _remote.handshake(serialNumber);
      await _local.saveTerminal(terminal);
      return Result<Terminal>.success(terminal);
    } on TerminalApiException catch (error) {
      return Result<Terminal>.failure(_failureOfCode(error));
    } on DioException catch (error) {
      return Result<Terminal>.failure(_failureOf(error));
    }
  }

  @override
  Future<void> forget() => _local.clear();

  @override
  Future<Result<Terminal>> acknowledgeSync(
    Map<String, dynamic> summary,
  ) async {
    try {
      final Terminal terminal = await _remote.acknowledgeSync(summary);
      await _local.saveTerminal(terminal);
      return Result<Terminal>.success(terminal);
    } on TerminalApiException catch (error) {
      return Result<Terminal>.failure(_failureOfCode(error));
    } on DioException catch (error) {
      return Result<Terminal>.failure(_failureOf(error));
    }
  }

  @override
  Future<void> logActivity(
    TerminalActivityType type, {
    Map<String, dynamic>? context,
  }) async {
    final DateTime localRecordedAt = _now();
    final int localId = await _local.recordActivity(
      type,
      context: context,
      localRecordedAt: localRecordedAt,
    );

    final String? serialNumber = await _local.readSerialNumber();
    if (serialNumber == null) return;

    try {
      await _remote.logActivity(serialNumber, type, context: context);
      await _local.markActivitySynced(localId);
    } on Object {
      // Best-effort (voir docstring `TerminalRepository.logActivity`) :
      // l'entrée reste en base, non synchronisée, pour un futur
      // rattrapage — jamais d'exception remontée à l'appelant (le scan ou
      // l'impression ne doit pas échouer pour un simple souci de journal).
    }
  }

  Failure _failureOfCode(TerminalApiException error) => switch (error.code) {
        TerminalApiException.unknown => TerminalUnknownFailure(error.message),
        TerminalApiException.lost ||
        TerminalApiException.maintenance ||
        TerminalApiException.inactive =>
          TerminalNotUsableFailure(error.code, error.message),
        _ => ServerFailure(error.message),
      };

  Failure _failureOf(DioException error) {
    final apiError = error.error;
    final message = apiError is ApiException ? apiError.message : null;

    return switch (apiError) {
      UnauthorizedApiException() =>
        AuthenticationFailure(message ?? 'Authentification requise.'),
      ForbiddenApiException() => TerminalNotUsableFailure(
          TerminalApiException.inactive,
          message ?? 'Ce terminal est désactivé. Contactez votre agence.',
        ),
      ValidationApiException() =>
        ValidationFailure(message ?? 'Numéro de série invalide.'),
      NetworkApiException() => const NetworkFailure(),
      TimeoutApiException() =>
        const NetworkFailure('Le serveur met trop de temps à répondre.'),
      ServerApiException() =>
        ServerFailure(message ?? 'Le serveur est momentanément indisponible.'),
      _ => ServerFailure(message ?? 'Une erreur est survenue.'),
    };
  }
}
