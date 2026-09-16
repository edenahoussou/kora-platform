// Le PDF officiel des billets : téléchargement, enregistrement, retry sûr,
// hors ligne et contrôle d'accès.

import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/ticket_document_remote_data_source.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/ticket_document_store.dart';
import 'package:kora_app_passenger/features/booking/data/repositories/ticket_document_repository_impl.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/ticket_document.dart';

/// Une source réseau pilotée par le test.
class _FakeRemote implements TicketDocumentRemoteDataSource {
  final List<FutureOr<TicketPdfPayload> Function()> answers = [];
  int calls = 0;

  @override
  Future<TicketPdfPayload> download(String ticketId) async {
    calls++;
    return answers.removeAt(0)();
  }
}

DioException _status(int code) {
  final options = RequestOptions(path: '/api/v1/tickets/x/pdf');
  return DioException(
    requestOptions: options,
    type: DioExceptionType.badResponse,
    response: Response<List<int>>(requestOptions: options, statusCode: code),
  );
}

DioException _offline() => DioException(
  requestOptions: RequestOptions(path: '/api/v1/tickets/x/pdf'),
  type: DioExceptionType.connectionError,
);

void main() {
  const ticketId = '9d2b6f7e-1c3a-4b8e-9f10-2a3b4c5d6e7f';
  final pdf = Uint8List.fromList('%PDF-1.7 officiel'.codeUnits);

  late Directory root;
  late _FakeRemote remote;
  late TicketDocumentRepositoryImpl repository;

  TicketPdfPayload payload([String name = 'KORA-K5F414H5.pdf']) =>
      TicketPdfPayload(bytes: pdf, fileName: name);

  List<FileSystemEntity> filesOf(String id) {
    final directory = Directory('${root.path}${Platform.pathSeparator}$id');
    return directory.existsSync() ? directory.listSync() : const [];
  }

  setUp(() {
    root = Directory.systemTemp.createTempSync('kora-tickets-');
    remote = _FakeRemote();
    repository = TicketDocumentRepositoryImpl(
      remote: remote,
      store: TicketDocumentStore(root: () async => root),
    );
  });

  tearDown(() => root.deleteSync(recursive: true));

  test(
    'enregistre le document du backend, sous le nom qu’il lui donne',
    () async {
      remote.answers.add(payload);

      final result = await repository.download(ticketId);

      final document = result.getOrElse(() => throw StateError('échec'));
      expect(document.fileName, 'KORA-K5F414H5.pdf');
      expect(document.isStale, isFalse);
      // Le même document que celui généré par KORA, octet pour octet.
      expect(File(document.path).readAsBytesSync(), pdf);
    },
  );

  test('deux demandes simultanées ne lancent qu’un téléchargement', () async {
    final server = Completer<TicketPdfPayload>();
    remote.answers.add(() => server.future);

    final first = repository.download(ticketId);
    final second = repository.download(ticketId);
    server.complete(payload());

    expect(await first, await second);
    expect(remote.calls, 1);
    expect(filesOf(ticketId), hasLength(1));
  });

  test('réessayer après un échec ne crée aucun doublon', () async {
    remote.answers
      ..add(() => throw _status(503))
      ..add(payload)
      ..add(() => payload('KORA-K5F414H5-v2.pdf'));

    final failed = await repository.download(ticketId);
    expect(failed.isLeft(), isTrue);
    expect(failed.fold((e) => e, (_) => null), TicketDocumentError.unavailable);
    // Rien d'écrit : ni fichier partiel, ni billet vide.
    expect(filesOf(ticketId), isEmpty);

    await repository.download(ticketId);
    await repository.download(ticketId);

    final files = filesOf(ticketId);
    expect(files, hasLength(1));
    expect(files.single.uri.pathSegments.last, 'KORA-K5F414H5-v2.pdf');
  });

  test('hors ligne, le billet déjà enregistré reste disponible', () async {
    remote.answers
      ..add(payload)
      ..add(() => throw _offline());

    final online = (await repository.download(
      ticketId,
    )).getOrElse(() => throw StateError('échec'));
    final offline = (await repository.download(
      ticketId,
    )).getOrElse(() => throw StateError('échec'));

    expect(offline.isStale, isTrue);
    expect(offline.path, online.path);
    expect(File(offline.path).readAsBytesSync(), pdf);
  });

  test('hors ligne sans billet enregistré : une erreur réseau', () async {
    remote.answers.add(() => throw _offline());

    final result = await repository.download(ticketId);

    expect(result.fold((e) => e, (_) => null), TicketDocumentError.network);
  });

  group('contrôle d’accès', () {
    Future<TicketDocumentError?> errorFor(int status) async {
      remote.answers.add(() => throw _status(status));
      final result = await repository.download(ticketId);
      return result.fold((e) => e, (_) => null);
    }

    test('401 : session expirée', () async {
      expect(await errorFor(401), TicketDocumentError.sessionExpired);
    });

    test('403 et 404 : billet inaccessible', () async {
      expect(await errorFor(403), TicketDocumentError.inaccessible);
      expect(await errorFor(404), TicketDocumentError.inaccessible);
    });

    test('un refus ne ressort jamais la copie locale', () async {
      // Le serveur vient de dire non : la copie enregistrée plus tôt ne
      // doit pas contourner ce refus.
      remote.answers
        ..add(payload)
        ..add(() => throw _status(403));

      await repository.download(ticketId);
      final result = await repository.download(ticketId);

      expect(
        result.fold((e) => e, (_) => null),
        TicketDocumentError.inaccessible,
      );
    });

    test(
      'un identifiant qui n’est pas un UUID ne part pas au serveur',
      () async {
        final result = await repository.download('../../etc');

        expect(
          result.fold((e) => e, (_) => null),
          TicketDocumentError.inaccessible,
        );
        expect(remote.calls, 0);
      },
    );
  });

  test('une réponse qui n’est pas un PDF : génération indisponible', () async {
    remote.answers.add(() => throw const FormatException('html'));

    final result = await repository.download(ticketId);

    expect(result.fold((e) => e, (_) => null), TicketDocumentError.unavailable);
  });
}
