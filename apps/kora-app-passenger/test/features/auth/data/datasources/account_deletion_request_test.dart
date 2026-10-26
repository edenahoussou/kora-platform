// La demande de suppression du compte, côté réseau (`DELETE auth/me`).
//
// Le serveur re-vérifie le PIN et borne le motif à 500 caractères. Un
// motif vide ne doit pas partir : il effacerait côté serveur celui d'une
// demande précédente, l'appel étant idempotent.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/constants/api_constants.dart';
import 'package:kora_app_passenger/features/auth/data/datasources/auth_remote_data_source.dart';
import 'package:mocktail/mocktail.dart';

class _MockDio extends Mock implements Dio {}

void main() {
  late _MockDio dio;
  late AuthRemoteDataSourceImpl dataSource;

  setUp(() {
    dio = _MockDio();
    dataSource = AuthRemoteDataSourceImpl(dio);

    when(() => dio.delete<void>(any(), data: any(named: 'data'))).thenAnswer(
      (_) async => Response<void>(
        requestOptions: RequestOptions(path: ApiConstants.me),
        statusCode: 202,
      ),
    );
  });

  Map<String, dynamic> lastBody() {
    final captured = verify(
      () => dio.delete<void>(ApiConstants.me, data: captureAny(named: 'data')),
    ).captured.last;

    return Map<String, dynamic>.from(captured as Map);
  }

  test('envoie le PIN et le motif sur `DELETE auth/me`', () async {
    await dataSource.requestAccountDeletion(
      pin: '1234',
      reason: '  Je ne voyage plus.  ',
    );

    expect(lastBody(), {'pin': '1234', 'reason': 'Je ne voyage plus.'});
  });

  test('un motif vide ne part pas', () async {
    await dataSource.requestAccountDeletion(pin: '1234', reason: '   ');

    expect(lastBody(), {'pin': '1234'});
  });
}
