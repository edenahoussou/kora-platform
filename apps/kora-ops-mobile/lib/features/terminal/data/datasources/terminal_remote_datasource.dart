import 'package:dio/dio.dart';

import '../../domain/entities/terminal.dart';
import '../../domain/entities/terminal_activity_type.dart';
import '../models/terminal_dtos.dart';

/// Erreur métier renvoyée par les endpoints `ops-mobile/terminal/*` sous
/// la forme `{ "error": { "code": "terminal_unknown", "message": "…" } }`
/// — même convention que `AuthApiException`.
class TerminalApiException implements Exception {
  const TerminalApiException(this.code, this.message);

  static const String unknown = 'terminal_unknown';
  static const String lost = 'terminal_lost';
  static const String maintenance = 'terminal_maintenance';
  static const String inactive = 'terminal_inactive';

  final String code;
  final String message;

  @override
  String toString() => 'TerminalApiException($code): $message';
}

/// Couche DATA : les appels réseau du terminal P501. Lève des exceptions ;
/// c'est le repository qui les traduit en `Failure`.
abstract class TerminalRemoteDataSource {
  Future<Terminal> handshake(String serialNumber);

  Future<Terminal> acknowledgeSync(Map<String, dynamic> summary);

  /// `serialNumber` en secours si l'en-tête `X-Terminal-Serial` n'a pas pu
  /// être posé (terminal pas encore relu depuis le stockage sécurisé au
  /// moment de l'appel) — voir `TerminalActivityRequest`, qui accepte les
  /// deux.
  Future<void> logActivity(
    String serialNumber,
    TerminalActivityType type, {
    Map<String, dynamic>? context,
  });
}

class TerminalRemoteDataSourceImpl implements TerminalRemoteDataSource {
  TerminalRemoteDataSourceImpl(this._dio, {this.telemetry});

  final Dio _dio;

  /// Télémétrie de l'appareil (`app_version`, `os_version`,
  /// `hardware_model`) ajoutée au handshake et à la sync — c'est elle qui
  /// permet au serveur de calculer le bloc `update` (MEZ-150). Optionnelle :
  /// sans elle, les corps envoyés restent ceux d'avant.
  final Future<Map<String, String>> Function()? telemetry;

  Future<Map<String, String>> _telemetry() async {
    final Future<Map<String, String>> Function()? provider = telemetry;
    if (provider == null) return const <String, String>{};

    try {
      return await provider();
    } on Object {
      return const <String, String>{};
    }
  }

  @override
  Future<Terminal> handshake(String serialNumber) async {
    final Map<String, String> telemetry = await _telemetry();
    final response = await _guard(
      () => _dio.post<dynamic>(
        '/api/v1/ops-mobile/terminal/handshake',
        data: <String, dynamic>{
          'serial_number': serialNumber,
          ...telemetry,
        },
      ),
    );
    return TerminalDtos.terminal(response.data);
  }

  @override
  Future<Terminal> acknowledgeSync(Map<String, dynamic> summary) async {
    final Map<String, String> telemetry = await _telemetry();
    final response = await _guard(
      () => _dio.post<dynamic>(
        '/api/v1/ops-mobile/terminal/sync',
        data: <String, dynamic>{'summary': summary, ...telemetry},
      ),
    );
    return TerminalDtos.terminal(response.data);
  }

  @override
  Future<void> logActivity(
    String serialNumber,
    TerminalActivityType type, {
    Map<String, dynamic>? context,
  }) {
    return _guard(
      () => _dio.post<dynamic>(
        '/api/v1/ops-mobile/terminal/activity',
        data: <String, dynamic>{
          'serial_number': serialNumber,
          'type': type.value,
          if (context != null) 'context': context,
        },
      ),
    );
  }

  /// Traduit les erreurs métier de l'API (`{ "error": { "code" } }`) avant
  /// qu'elles ne remontent — le reste (401/403 sans code métier/5xx/
  /// réseau) est déjà traduit par l'`ErrorInterceptor` du package réseau,
  /// plus haut dans la chaîne (voir `AuthRemoteDataSourceImpl._guard`,
  /// même convention).
  Future<T> _guard<T>(Future<T> Function() call) async {
    try {
      return await call();
    } on DioException catch (error) {
      final code = TerminalDtos.errorCode(error.response?.data);
      if (code == null) rethrow;

      throw TerminalApiException(
        code,
        TerminalDtos.errorMessage(error.response?.data) ??
            'Erreur du terminal.',
      );
    }
  }
}
