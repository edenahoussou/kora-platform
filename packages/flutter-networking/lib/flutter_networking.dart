/// Couche réseau KORA — point d'entrée public du package.
///
/// ```dart
/// import 'package:flutter_networking/flutter_networking.dart';
/// ```
library;

export 'package:dio/dio.dart'
    show Dio, DioException, DioExceptionType, Interceptor, InterceptorsWrapper;

export 'src/cache/cache_interceptor.dart';
export 'src/cache/network_cache.dart';
export 'src/config/network_config.dart';
export 'src/dio_client.dart';
export 'src/errors/api_exception.dart';
export 'src/errors/api_exception_mapper.dart';
export 'src/interceptors/auth_interceptor.dart';
export 'src/interceptors/error_interceptor.dart';
export 'src/interceptors/logging_interceptor.dart';
export 'src/interceptors/retry_interceptor.dart';
export 'src/models/api_result.dart';
export 'src/models/paginated_response.dart';
export 'src/network_info.dart';
export 'src/offline/offline_request_queue.dart';
