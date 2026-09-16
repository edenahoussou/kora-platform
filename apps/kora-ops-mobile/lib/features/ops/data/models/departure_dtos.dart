import '../../domain/entities/crew.dart';
import '../../domain/entities/depart.dart';
import '../../domain/entities/stop.dart';

/// Couche DATA : lecture de `GET /ops/departures` et `GET /ops/departures/
/// {uuid}` — miroir de `OpsDepartureResource` côté backend.
abstract final class DepartureDtos {
  static Map<String, dynamic> unwrap(Object? body) {
    if (body is! Map) return const <String, dynamic>{};
    final data = body['data'];
    if (data is Map) return Map<String, dynamic>.from(data);
    return Map<String, dynamic>.from(body);
  }

  /// Ligne de `GET /ops/departures` (liste) — `stops` y est toujours
  /// `null` (voir doc backend), `route` retombe donc sur le nom de ligne.
  static Depart fromIndexRow(Map<String, dynamic> row) => _fromJson(row);

  /// `GET /ops/departures/{uuid}` (détail) — `stops` est renseigné,
  /// `route` en est dérivé (premier arrêt → dernier arrêt).
  static Depart fromShow(Object? body) => _fromJson(unwrap(body));

  static List<Depart> list(Object? body) {
    final rows = body is Map ? body['data'] : null;
    if (rows is! List) return const <Depart>[];

    return rows
        .whereType<Map<dynamic, dynamic>>()
        .map((row) => fromIndexRow(Map<String, dynamic>.from(row)))
        .toList();
  }

  static Depart _fromJson(Map<String, dynamic> json) {
    final line = json['line'];
    final lineName = line is Map ? line['name'] as String? : null;
    final lineCode = line is Map ? line['code'] as String? : null;

    final rawStops = json['stops'];
    final List<Stop> stops = rawStops is List
        ? rawStops
            .whereType<Map<dynamic, dynamic>>()
            .map((s) => Stop(
                  uuid: '${s['uuid']}',
                  name: s['name'] as String? ?? '',
                  city: s['city'] as String?,
                  country: s['country'] as String?,
                  order: s['order'] as int?,
                ))
            .toList()
        : const <Stop>[];

    final bus = json['bus'];
    final String vehiculeImmat =
        bus is Map ? (bus['registrationNumber'] as String? ?? '—') : '—';
    final int totalPlaces = bus is Map ? (bus['seatCount'] as int? ?? 0) : 0;

    final String route = stops.length >= 2
        ? '${stops.first.name} → ${stops.last.name}'
        : (lineName ?? '—');

    final String departureDate = json['departureDate'] as String? ?? '';
    final String departureTime = json['departureTime'] as String? ?? '';

    return Depart(
      id: '${json['uuid']}',
      route: route,
      date: _formatDate(departureDate),
      heure: departureTime,
      vehiculeImmat: vehiculeImmat,
      totalPlaces: totalPlaces,
      placesOccupees: 0,
      lineCode: lineCode,
      lineName: lineName,
      status: json['status'] as String?,
      stops: stops,
      crew: _crewOf(json['crew']),
      manifestVersion: json['manifestVersion'] as String?,
    );
  }

  static Crew? _crewOf(Object? raw) {
    if (raw is! Map) return null;

    CrewMember? memberOf(Object? value) {
      if (value is! Map || value['uuid'] == null) return null;
      return CrewMember(
        uuid: '${value['uuid']}',
        name: value['name'] as String? ?? '',
      );
    }

    return Crew(
      driver: memberOf(raw['driver']),
      secondaryDriver: memberOf(raw['secondaryDriver']),
      conductor: memberOf(raw['conductor']),
      mechanic: memberOf(raw['mechanic']),
    );
  }

  /// `2026-10-01` → `01/10/2026` (format déjà affiché par les écrans
  /// existants — voir MockOpsDataSource).
  static String _formatDate(String isoDate) {
    final parts = isoDate.split('-');
    if (parts.length != 3) return isoDate;
    return '${parts[2]}/${parts[1]}/${parts[0]}';
  }
}
