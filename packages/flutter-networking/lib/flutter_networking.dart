/// Couche réseau KORA — point d'entrée public du package.
///
/// ```dart
/// import 'package:flutter_networking/flutter_networking.dart';
/// ```
library;

export 'package:dio/dio.dart' show Dio, DioException, Interceptor, InterceptorsWrapper;

export 'src/dio_client.dart';
export 'src/network_info.dart';
