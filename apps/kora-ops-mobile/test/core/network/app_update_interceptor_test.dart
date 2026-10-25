import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/network/app_update_interceptor.dart';
import 'package:kora_ops_mobile/features/app_update/domain/entities/app_update_info.dart';

class _RecordingHandler extends ResponseInterceptorHandler {
  Response<dynamic>? forwarded;

  @override
  void next(Response<dynamic> response) => forwarded = response;
}

Response<dynamic> _response(String path, Object? data) => Response<dynamic>(
      requestOptions: RequestOptions(
        path: path,
        baseUrl: 'https://ops.koraexpress.app',
      ),
      data: data,
      statusCode: 200,
    );

const Map<String, dynamic> _block = <String, dynamic>{
  'latest_version': '1.4.0',
  'latest_build': 42,
  'mandatory': true,
  'release_uuid': 'r-1',
  'download_url':
      'https://ops.koraexpress.app/downloads/apps/ops-mobile/releases/r-1',
  'sha256': 'abc',
};

void main() {
  late List<AppUpdateInfo?> received;
  late AppUpdateInterceptor interceptor;

  setUp(() {
    received = <AppUpdateInfo?>[];
    interceptor = AppUpdateInterceptor(onUpdateBlock: received.add);
  });

  test('transmet le bloc update des réponses handshake/sync/activity', () {
    for (final String path in <String>[
      '/api/v1/ops-mobile/terminal/handshake',
      '/api/v1/ops-mobile/terminal/sync',
      '/api/v1/ops-mobile/terminal/activity',
    ]) {
      final handler = _RecordingHandler();
      final response = _response(path, <String, dynamic>{
        'data': <String, dynamic>{'update': _block},
      });

      interceptor.onResponse(response, handler);

      expect(handler.forwarded, same(response));
    }

    expect(received, hasLength(3));
    expect(received.every((info) => info?.serverMandatory ?? false), isTrue);
  });

  test('transmet null quand le serveur indique qu’aucune release n’existe', () {
    interceptor.onResponse(
      _response('/api/v1/ops-mobile/terminal/handshake', <String, dynamic>{
        'data': <String, dynamic>{'update': null},
      }),
      _RecordingHandler(),
    );

    expect(received, <AppUpdateInfo?>[null]);
  });

  test('ignore les autres endpoints, les réponses sans bloc et les 204', () {
    interceptor
      ..onResponse(
        _response('/api/v1/ops/departures', <String, dynamic>{
          'data': <String, dynamic>{'update': _block},
        }),
        _RecordingHandler(),
      )
      ..onResponse(
        _response('/api/v1/ops-mobile/terminal/sync', <String, dynamic>{
          'data': <String, dynamic>{'uuid': 't-1'},
        }),
        _RecordingHandler(),
      )
      ..onResponse(
        _response('/api/v1/ops-mobile/terminal/activity', ''),
        _RecordingHandler(),
      );

    expect(received, isEmpty);
  });
}
