import 'dart:async';
import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../../../core/constants/storage_keys.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/assisted_booking.dart';
import '../models/assisted_booking_model.dart';

/// Couche DATA : les réservations confiées à une agence depuis ce
/// téléphone.
///
/// L'API ne liste pas les dossiers d'un voyageur ; sans cette trace, il
/// ne retrouverait sa demande nulle part après l'écran de confirmation.
/// Rien de sensible : une référence, un trajet, des numéros de sièges — le
/// serveur refuse de toute façon un dossier qui n'est pas le sien.
class AssistedBookingLocalDataSource {
  AssistedBookingLocalDataSource(this._prefs);

  final SharedPreferences _prefs;

  final _changes = StreamController<void>.broadcast();

  /// Émet après chaque écriture.
  Stream<void> get changes => _changes.stream;

  /// Au-delà, les plus anciennes demandes tombent : la liste reste courte,
  /// et une demande de plus de quelques semaines est close depuis
  /// longtemps.
  static const _limit = 20;

  List<AssistanceRecord> records() {
    final raw = _prefs.getString(StorageKeys.assistedBookings);
    if (raw == null) return const [];

    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      return [
        for (final item in decoded)
          if (item is Map) recordFromJson(ApiJson.map(item)),
      ];
    } on FormatException {
      // Une trace illisible ne doit pas bloquer l'onglet Voyages : on la
      // perd plutôt que de planter.
      return const [];
    }
  }

  /// Ajoute la demande en tête, ou la remplace si elle y est déjà : un
  /// nouvel essai sur la même réservation ne doit pas la dédoubler.
  Future<void> remember(AssistanceRecord record) async {
    final others = records().where(
      (existing) => existing.reservationId != record.reservationId,
    );
    final kept = [record, ...others].take(_limit);

    await _prefs.setString(
      StorageKeys.assistedBookings,
      jsonEncode([for (final item in kept) recordToJson(item)]),
    );
    _changes.add(null);
  }
}
