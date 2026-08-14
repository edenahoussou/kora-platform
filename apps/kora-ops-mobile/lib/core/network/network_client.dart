import 'package:dio/dio.dart';
import '../exceptions/app_exceptions.dart';
import '../utils/app_logger.dart';

/// Enveloppe autour de Dio. La couche Presentation ne doit jamais importer
/// `package:dio` directement — uniquement [NetworkClient] et les
/// exceptions de `core/exceptions`.
class NetworkClient {
  NetworkClient({required String baseUrl, required bool enableLogging}) {
    _dio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 20),
        headers: <String, String>{'Content-Type': 'application/json'},
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (RequestOptions options, RequestInterceptorHandler handler) {
          if (enableLogging) {
            AppLogger.d('→ ${options.method} ${options.uri}');
          }
          handler.next(options);
        },
        onResponse:
            (Response<dynamic> response, ResponseInterceptorHandler handler) {
          if (enableLogging) {
            AppLogger.d(
                '← ${response.statusCode} ${response.requestOptions.uri}');
          }
          handler.next(response);
        },
        onError: (DioException error, ErrorInterceptorHandler handler) {
          if (enableLogging) {
            AppLogger.e('✕ ${error.requestOptions.uri}', error);
          }
          handler.next(error);
        },
      ),
    );
  }

  late final Dio _dio;

  /// Ajoute un intercepteur externe (ex : injection du token d'auth).
  void addInterceptor(Interceptor interceptor) =>
      _dio.interceptors.add(interceptor);

  Future<Response<dynamic>> get(String path,
          {Map<String, dynamic>? queryParameters}) =>
      _request(() => _dio.get<dynamic>(path, queryParameters: queryParameters));

  Future<Response<dynamic>> post(String path, {Object? data}) =>
      _request(() => _dio.post<dynamic>(path, data: data));

  Future<Response<dynamic>> put(String path, {Object? data}) =>
      _request(() => _dio.put<dynamic>(path, data: data));

  Future<Response<dynamic>> delete(String path) =>
      _request(() => _dio.delete<dynamic>(path));

  Future<Response<dynamic>> _request(
      Future<Response<dynamic>> Function() call) async {
    try {
      return await call();
    } on DioException catch (error) {
      throw _mapDioException(error);
    }
  }

  AppException _mapDioException(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.transformTimeout:
      case DioExceptionType.connectionError:
        return const NetworkException();

      case DioExceptionType.badResponse:
        final int? status = error.response?.statusCode;

        if (status == 401 || status == 403) {
          return const UnauthorizedException();
        }

        if (status != null && status >= 500) {
          return const ServerException();
        }

        return ApiException(
          error.response?.statusMessage ?? 'Requête invalide.',
          statusCode: status,
        );

      case DioExceptionType.cancel:
      case DioExceptionType.badCertificate:
      case DioExceptionType.unknown:
        return ApiException(
          error.message ?? 'Erreur réseau inconnue.',
        );
    }
  }
}
