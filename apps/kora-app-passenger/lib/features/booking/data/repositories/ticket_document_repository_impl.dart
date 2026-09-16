import 'dart:async';
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
        UnauthorizedApiException;

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
  final Map<String, Future<Either<TicketDocumentError, TicketDocument>>>
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
  Future<Either<TicketDocumentError, TicketDocument>> download(
    String ticketId,
  ) {
    final running = _inFlight[ticketId];
    if (running != null) return running;

    final future = _download(ticketId);
    _inFlight[ticketId] = future;
    unawaited(future.whenComplete(() => _inFlight.remove(ticketId)));

    return future;
  }

  Future<Either<TicketDocumentError, TicketDocument>> _download(
    String ticketId,
  ) async {
    // Vérifié avant l'appel : un identifiant mal formé ne doit pas même
    // partir vers le serveur.
    if (!TicketDocumentStore.isValidTicketId(ticketId)) {
      return const Left(TicketDocumentError.inaccessible);
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
      final kind = errorOf(ApiExceptionMapper.fromDioException(error));
      return _orSaved(ticketId, kind);
    } on FormatException {
      return const Left(TicketDocumentError.unavailable);
    } on ArgumentError {
      // Un identifiant qui n'est pas un UUID : aucune requête n'est partie.
      return const Left(TicketDocumentError.inaccessible);
    } on FileSystemException {
      return const Left(TicketDocumentError.unexpected);
    } on Object {
      return const Left(TicketDocumentError.unexpected);
    }
  }

  /// Hors ligne, le billet déjà enregistré vaut mieux qu'une erreur : c'est
  /// le même document, et c'est à l'embarquement qu'on en a besoin — là où
  /// le réseau manque le plus.
  ///
  /// Seulement hors ligne : sur un 401 ou un 403, ressortir la copie locale
  /// contournerait le contrôle d'accès que le serveur vient d'exprimer.
  Future<Either<TicketDocumentError, TicketDocument>> _orSaved(
    String ticketId,
    TicketDocumentError kind,
  ) async {
    if (kind == TicketDocumentError.network) {
      final document = await saved(ticketId);
      if (document != null) return Right(document.asStale());
    }

    return Left(kind);
  }

  /// La traduction des erreurs réseau en suites possibles pour l'écran.
  static TicketDocumentError errorOf(ApiException error) => switch (error) {
    NetworkApiException() ||
    TimeoutApiException() => TicketDocumentError.network,
    UnauthorizedApiException() => TicketDocumentError.sessionExpired,
    ForbiddenApiException() ||
    NotFoundApiException() => TicketDocumentError.inaccessible,
    ServerApiException() => TicketDocumentError.unavailable,
    _ => TicketDocumentError.unexpected,
  };
}
