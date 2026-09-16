import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../../core/constants/storage_keys.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../domain/entities/crew.dart';
import '../../domain/entities/depart.dart';
import '../../domain/entities/stop.dart';

/// Couche DATA : persistance locale du départ actuellement sélectionné
/// (stockage sécurisé — même raison que `TerminalLocalDataSource` : ce
/// n'est pas un secret, mais ça doit survivre à un redémarrage à froid du
/// terminal — voir `SelectedDepartCubit.bootstrap`, MEZ-92).
abstract class SelectedDepartureLocalDataSource {
  Future<Depart?> readSnapshot();
  Future<void> saveSnapshot(Depart depart);
  Future<void> clear();
}

class SelectedDepartureLocalDataSourceImpl
    implements SelectedDepartureLocalDataSource {
  SelectedDepartureLocalDataSourceImpl(this._storage);

  final FlutterSecureStorage _storage;

  @override
  Future<Depart?> readSnapshot() async {
    try {
      final String? raw =
          await _storage.read(key: StorageKeys.selectedDepartureSnapshot);
      if (raw == null || raw.isEmpty) return null;

      return _fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } on Object catch (error) {
      throw CacheException('Départ sélectionné local illisible : $error');
    }
  }

  @override
  Future<void> saveSnapshot(Depart depart) async {
    try {
      await _storage.write(
        key: StorageKeys.selectedDepartureSnapshot,
        value: jsonEncode(_toJson(depart)),
      );
    } on Object catch (error) {
      throw CacheException(
          'Impossible d’enregistrer le départ sélectionné : $error');
    }
  }

  @override
  Future<void> clear() async {
    await _storage.delete(key: StorageKeys.selectedDepartureSnapshot);
  }

  Map<String, dynamic> _toJson(Depart depart) => <String, dynamic>{
        'id': depart.id,
        'route': depart.route,
        'date': depart.date,
        'heure': depart.heure,
        'vehiculeImmat': depart.vehiculeImmat,
        'totalPlaces': depart.totalPlaces,
        'placesOccupees': depart.placesOccupees,
        'lineCode': depart.lineCode,
        'lineName': depart.lineName,
        'status': depart.status,
        'stops': depart.stops
            .map((s) => <String, dynamic>{
                  'uuid': s.uuid,
                  'name': s.name,
                  'city': s.city,
                  'country': s.country,
                  'order': s.order,
                })
            .toList(),
        'crew': depart.crew == null
            ? null
            : <String, dynamic>{
                'driver': _memberJson(depart.crew!.driver),
                'secondaryDriver': _memberJson(depart.crew!.secondaryDriver),
                'conductor': _memberJson(depart.crew!.conductor),
                'mechanic': _memberJson(depart.crew!.mechanic),
              },
        'manifestVersion': depart.manifestVersion,
      };

  Map<String, dynamic>? _memberJson(CrewMember? member) => member == null
      ? null
      : <String, dynamic>{'uuid': member.uuid, 'name': member.name};

  Depart _fromJson(Map<String, dynamic> json) {
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

    CrewMember? memberOf(Object? value) {
      if (value is! Map || value['uuid'] == null) return null;
      return CrewMember(
        uuid: '${value['uuid']}',
        name: value['name'] as String? ?? '',
      );
    }

    final rawCrew = json['crew'];
    final Crew? crew = rawCrew is Map
        ? Crew(
            driver: memberOf(rawCrew['driver']),
            secondaryDriver: memberOf(rawCrew['secondaryDriver']),
            conductor: memberOf(rawCrew['conductor']),
            mechanic: memberOf(rawCrew['mechanic']),
          )
        : null;

    return Depart(
      id: json['id'] as String? ?? '',
      route: json['route'] as String? ?? '—',
      date: json['date'] as String? ?? '',
      heure: json['heure'] as String? ?? '',
      vehiculeImmat: json['vehiculeImmat'] as String? ?? '—',
      totalPlaces: json['totalPlaces'] as int? ?? 0,
      placesOccupees: json['placesOccupees'] as int? ?? 0,
      lineCode: json['lineCode'] as String?,
      lineName: json['lineName'] as String?,
      status: json['status'] as String?,
      stops: stops,
      crew: crew,
      manifestVersion: json['manifestVersion'] as String?,
    );
  }
}
