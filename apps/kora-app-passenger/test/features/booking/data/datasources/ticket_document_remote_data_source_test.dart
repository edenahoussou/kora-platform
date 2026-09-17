// Le PDF officiel, tel que l'API le rend — contrat provisoire MEZ-82.

import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/constants/api_constants.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/ticket_document_remote_data_source.dart';
import 'package:mocktail/mocktail.dart';

class _MockDio extends Mock implements Dio {}

void main() {
  setUpAll(() => registerFallbackValue(Options()));

  const ticketId = '9d2b6f7e-1c3a-4b8e-9f10-2a3b4c5d6e7f';
  final pdf = Uint8List.fromList('%PDF-1.7\n…'.codeUnits);

  late _MockDio dio;
  late TicketDocumentRemoteDataSource dataSource;

  setUp(() {
    dio = _MockDio();
    dataSource = TicketDocumentRemoteDataSource(dio);
  });

  void stubResponse(List<int> bytes, {String? disposition}) {
    when(
      () => dio.get<List<int>>(any(), options: any(named: 'options')),
    ).thenAnswer(
      (_) async => Response<List<int>>(
        requestOptions: RequestOptions(path: ApiConstants.ticketPdf(ticketId)),
        statusCode: 200,
        data: bytes,
        headers: Headers.fromMap({
          'content-type': ['application/pdf'],
          'content-disposition': ?disposition == null ? null : [disposition],
        }),
      ),
    );
  }

  test('demande le PDF par l’UUID du billet, en flux binaire', () async {
    stubResponse(pdf, disposition: 'attachment; filename="KORA-K5F414H5.pdf"');

    final payload = await dataSource.download(ticketId);

    final captured = verify(
      () => dio.get<List<int>>(
        captureAny(),
        options: captureAny(named: 'options'),
      ),
    ).captured;
    expect(captured[0], '/api/v1/tickets/$ticketId/pdf');
    final options = captured[1] as Options;
    // Décodé en texte, un PDF est corrompu.
    expect(options.responseType, ResponseType.bytes);
    expect(options.headers?['Accept'], 'application/pdf');

    // Le document du backend, octet pour octet, et le nom qu'il lui donne.
    expect(payload.bytes, pdf);
    expect(payload.fileName, 'KORA-K5F414H5.pdf');
  });

  test('refuse une réponse qui n’est pas un PDF', () async {
    // Une page d'erreur HTML servie en 200 ne doit jamais devenir un billet.
    stubResponse('<html>Maintenance</html>'.codeUnits);

    expect(
      () => dataSource.download(ticketId),
      throwsA(isA<FormatException>()),
    );
  });

  group('nom de fichier', () {
    test('`filename*` porte les accents et l’emporte', () {
      expect(
        TicketDocumentRemoteDataSource.fileNameOf(
          "attachment; filename=\"billet.pdf\"; filename*=UTF-8''Billet%20B%C3%A9nin.pdf",
        ),
        'Billet Bénin.pdf',
      );
    });

    test('`filename` sans guillemets', () {
      expect(
        TicketDocumentRemoteDataSource.fileNameOf(
          'attachment; filename=KORA-K5F414H5.pdf',
        ),
        'KORA-K5F414H5.pdf',
      );
    });

    test('pas d’en-tête, pas de nom', () {
      expect(TicketDocumentRemoteDataSource.fileNameOf(null), isNull);
      expect(TicketDocumentRemoteDataSource.fileNameOf('inline'), isNull);
    });
  });
}
