import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../../../core/constants/storage_keys.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/group_ticket.dart';
import '../models/group_ticket_model.dart';

/// Couche DATA : les réponses du serveur gardées telles quelles sur le
/// téléphone, pour que l'app reste lisible sans réseau.
///
/// Deux choses seulement, celles dont dépend l'embarquement : les billets
/// — l'onglet Voyages est la porte d'entrée du QR, et sans lui le billet
/// devient injoignable (APP-PAY-006) — et le catalogue des villes, sans
/// lequel l'accueil refuse de s'ouvrir.
///
/// Le JSON n'est pas transformé : relu par les mêmes modèles, il rend les
/// mêmes objets qu'au moment où il est arrivé.
class BookingCacheLocalDataSource {
  const BookingCacheLocalDataSource(this._prefs);

  final SharedPreferences _prefs;

  /// Les billets gardés, dans la forme où le serveur les a rendus. Vide
  /// quand le voyageur n'a encore rien chargé sur ce téléphone.
  List<Map<String, dynamic>> tickets() => _list(StorageKeys.tickets);

  /// Remplace la copie des billets. Une réponse vide n'écrase rien : un
  /// voyageur sans billet ce jour-là ne doit pas perdre ceux qu'il
  /// gardait.
  Future<void> rememberTickets(List<Map<String, dynamic>> entries) =>
      _remember(StorageKeys.tickets, entries);

  /// Les villes desservies, telles que `GET bus/cities` les a rendues.
  List<Map<String, dynamic>> cities() => _list(StorageKeys.cities);

  Future<void> rememberCities(List<Map<String, dynamic>> entries) =>
      _remember(StorageKeys.cities, entries);

  /// Les QR d'une réservation, marqués [GroupTicket.isStale] — l'écran
  /// peut alors dire qu'il montre une copie.
  List<GroupTicket> groupTickets(String reservationId) {
    final entries = _map(StorageKeys.groupTickets)[reservationId];
    if (entries is! List) return const [];

    return [
      for (final entry in entries)
        if (entry is Map) localGroupTicketFromJson(ApiJson.map(entry)),
    ];
  }

  /// Remplace les QR de cette réservation.
  ///
  /// Une liste vide n'écrase rien : l'émission suit le paiement, et un
  /// écran ouvert trop tôt ne doit pas effacer des billets déjà gardés.
  Future<void> rememberGroupTickets(
    String reservationId,
    List<GroupTicket> tickets,
  ) async {
    if (reservationId.isEmpty || tickets.isEmpty) return;

    final stored = _map(StorageKeys.groupTickets)
      ..remove(reservationId)
      ..[reservationId] = [
        for (final ticket in tickets) localGroupTicketToJson(ticket),
      ];

    // Les plus récentes d'abord : `Map` garde l'ordre d'insertion, la
    // réservation réécrite revient donc en fin de liste.
    final kept = stored.entries.toList();
    final dropped = kept.length - _groupLimit;
    await _prefs.setString(
      StorageKeys.groupTickets,
      jsonEncode(Map.fromEntries(dropped > 0 ? kept.sublist(dropped) : kept)),
    );
  }

  /// Au-delà, la plus ancienne réservation tombe : on garde de quoi
  /// embarquer, pas un historique.
  static const _groupLimit = 10;

  Map<String, dynamic> _map(String key) {
    final raw = _prefs.getString(key);
    if (raw == null) return {};

    try {
      final decoded = jsonDecode(raw);
      return decoded is Map ? ApiJson.map(decoded) : {};
    } on FormatException {
      return {};
    }
  }

  List<Map<String, dynamic>> _list(String key) {
    final raw = _prefs.getString(key);
    if (raw == null) return const [];

    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      return [
        for (final entry in decoded)
          if (entry is Map) ApiJson.map(entry),
      ];
    } on FormatException {
      // Une copie illisible ne doit pas empêcher d'aller chercher la
      // donnée en ligne : on la perd plutôt que de planter.
      return const [];
    }
  }

  Future<void> _remember(String key, List<Map<String, dynamic>> entries) async {
    if (entries.isEmpty) return;

    await _prefs.setString(key, jsonEncode(entries));
  }
}
