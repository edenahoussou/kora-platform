import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:dartz/dartz.dart';
import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show
        ApiException,
        ApiExceptionMapper,
        ForbiddenApiException,
        NetworkApiException,
        NotFoundApiException,
        ServerApiException,
        TimeoutApiException,
        UnauthorizedApiException,
        ValidationApiException;

import '../../domain/entities/ticket_document.dart';
import '../../domain/repositories/ticket_document_repository.dart';
import '../datasources/ticket_document_remote_data_source.dart';
import '../datasources/ticket_document_store.dart';

class TicketDocumentRepositoryImpl implements TicketDocumentRepository {
  TicketDocumentRepositoryImpl({required this.remote, required this.store});

  final TicketDocumentRemoteDataSource remote;
  final TicketDocumentStore store;

  /// Les téléchargements en cours, par billet.
  ///
  /// Un second appel pendant le premier — deux appuis, ou la liste et le
  /// détail à la fois — reçoit la même réponse au lieu de relancer la
  /// génération et d'écrire deux fois le même fichier.
  final Map<String, Future<Either<TicketDocumentFailure, TicketDocument>>>
  _inFlight = {};

  @override
  Future<TicketDocument?> saved(String ticketId) async {
    try {
      return await store.find(ticketId);
    } on Object {
      return null;
    }
  }

  @override
  Future<Either<TicketDocumentFailure, TicketDocument>> download(
    String ticketId,
  ) {
    final running = _inFlight[ticketId];
    if (running != null) return running;

    final future = _download(ticketId);
    _inFlight[ticketId] = future;
    unawaited(future.whenComplete(() => _inFlight.remove(ticketId)));

    return future;
  }

  Future<Either<TicketDocumentFailure, TicketDocument>> _download(
    String ticketId,
  ) async {
    // Vérifié avant l'appel : un identifiant mal formé ne doit pas même
    // partir vers le serveur.
    if (!TicketDocumentStore.isValidTicketId(ticketId)) {
      return const Left(
        TicketDocumentFailure(TicketDocumentError.inaccessible),
      );
    }

    try {
      final payload = await remote.download(ticketId);
      final document = await store.save(
        ticketId,
        bytes: payload.bytes,
        fileName: payload.fileName,
      );

      return Right(document);
    } on DioException catch (error) {
      return _orSaved(ticketId, failureOf(error));
    } on FormatException catch (error) {
      // Un 200 dont le corps n'est pas un PDF : on dit ce qui est arrivé.
      return Left(
        TicketDocumentFailure(
          TicketDocumentError.unavailable,
          status: 200,
          message: error.message,
        ),
      );
    } on ArgumentError {
      // Un identifiant qui n'est pas un UUID : aucune requête n'est partie.
      return const Left(
        TicketDocumentFailure(TicketDocumentError.inaccessible),
      );
    } on FileSystemException catch (error) {
      return Left(
        TicketDocumentFailure(
          TicketDocumentError.unexpected,
          message: error.message,
        ),
      );
    } on Object {
      return const Left(TicketDocumentFailure(TicketDocumentError.unexpected));
    }
  }

  /// Hors ligne, le billet déjà enregistré vaut mieux qu'une erreur : c'est
  /// le même document, et c'est à l'embarquement qu'on en a besoin — là où
  /// le réseau manque le plus.
  ///
  /// Seulement hors ligne : sur un 401 ou un 403, ressortir la copie locale
  /// contournerait le contrôle d'accès que le serveur vient d'exprimer.
  Future<Either<TicketDocumentFailure, TicketDocument>> _orSaved(
    String ticketId,
    TicketDocumentFailure failure,
  ) async {
    if (failure.kind == TicketDocumentError.network) {
      final document = await saved(ticketId);
      if (document != null) return Right(document.asStale());
    }

    return Left(failure);
  }

  /// L'échec, avec la réponse du serveur lue dans son corps.
  ///
  /// Le téléchargement demande des octets : une erreur arrive donc elle
  /// aussi en octets, et `ApiExceptionMapper` n'y trouve aucun message. On
  /// décode ici le JSON de l'API (`{"error": {"code", "message"}}`).
  static TicketDocumentFailure failureOf(DioException error) {
    final response = error.response;
    final body = _jsonOf(response?.data);
    final nested = body['error'];
    final details = nested is Map ? nested : const <String, Object?>{};

    final message = details['message'] ?? body['message'];
    final code = details['code'];

    return TicketDocumentFailure(
      errorOf(ApiExceptionMapper.fromDioException(error)),
      status: response?.statusCode,
      code: code is String && code.isNotEmpty ? code : null,
      message: message is String && message.isNotEmpty ? message : null,
    );
  }

  static Map<Object?, Object?> _jsonOf(Object? data) {
    try {
      final decoded = switch (data) {
        final Map<Object?, Object?> map => map,
        final List<int> bytes => jsonDecode(
          utf8.decode(bytes, allowMalformed: true),
        ),
        final String text => jsonDecode(text),
        _ => null,
      };
      return decoded is Map ? decoded : const {};
    } on FormatException {
      return const {};
    }
  }

  /// La traduction des erreurs réseau en suites possibles pour l'écran.
  static TicketDocumentError errorOf(ApiException error) => switch (error) {
    NetworkApiException() ||
    TimeoutApiException() => TicketDocumentError.network,
    UnauthorizedApiException() => TicketDocumentError.sessionExpired,
    ForbiddenApiException() ||
    NotFoundApiException() => TicketDocumentError.inaccessible,
    ServerApiException() => TicketDocumentError.unavailable,
    // `ticket_pdf_unavailable` : billet annulé, remboursé ou non émis.
    ValidationApiException() => TicketDocumentError.notDownloadable,
    _ => TicketDocumentError.unexpected,
  };
}
