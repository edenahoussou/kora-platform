import '../../domain/entities/terminal.dart';
import '../../domain/entities/terminal_status.dart';

/// Couche DATA : lecture des réponses JSON des endpoints
/// `ops-mobile/terminal/*` — miroir de `TerminalP501Resource` côté
/// backend. Même règle de tolérance que `AuthDtos` : enveloppe `data`
/// optionnelle.
abstract final class TerminalDtos {
  static Map<String, dynamic> unwrap(Object? body) {
    if (body is! Map) return const <String, dynamic>{};

    final data = body['data'];
    if (data is Map) return Map<String, dynamic>.from(data);

    return Map<String, dynamic>.from(body);
  }

  static Terminal terminal(Object? body) {
    final data = unwrap(body);
    final agency = data['agency'];
    final assignedUser = data['assigned_user'];

    return Terminal(
      uuid: '${data['uuid']}',
      serialNumber: data['serial_number'] as String? ?? '',
      status: TerminalStatus.fromValue(data['status'] as String?),
      agencyName: agency is Map ? agency['name'] as String? : null,
      assignedUserName:
          assignedUser is Map ? assignedUser['name'] as String? : null,
      lastHandshakeAt: _date(data['last_handshake_at']),
      lastSyncAt: _date(data['last_sync_at']),
    );
  }

  /// Code métier d'une erreur (`terminal_unknown`, `terminal_lost`…), dans
  /// l'enveloppe `{ "error": { "code" } }` — même format que
  /// `AuthDtos.errorCode`.
  static String? errorCode(Object? body) {
    if (body is! Map) return null;

    final error = body['error'];
    if (error is! Map) return null;

    final code = error['code'];
    return code is String ? code : null;
  }

  static String? errorMessage(Object? body) {
    if (body is! Map) return null;

    final error = body['error'];
    if (error is Map && error['message'] is String) {
      return error['message'] as String;
    }

    return null;
  }

  static DateTime? _date(Object? value) {
    if (value is! String || value.isEmpty) return null;
    return DateTime.tryParse(value);
  }
}
