import '../../../ops/domain/entities/passenger.dart';

/// Couche DATA : lecture de `GET /ops/departures/{uuid}/manifest` — miroir
/// de `OpsManifestRowResource` côté backend. Ne contient jamais de prix —
/// l'API elle-même n'en renvoie pas sur cet endpoint.
abstract final class ManifesteDtos {
  /// Une ligne sans ticket résolu (`ticketUuid` null) ne peut pas être
  /// embarquée ni affichée utilement (voir `OpsManifestRowResource`,
  /// `$this->ticket?->uuid`) — filtrée par l'appelant plutôt que mappée en
  /// `Passenger` avec des champs vides.
  static Passenger? passenger(Map<String, dynamic> row) {
    final String? ticketUuid = row['ticketUuid'] as String?;
    if (ticketUuid == null) return null;

    final origin = row['originStop'];
    final destination = row['destinationStop'];
    final document = row['document'];

    return Passenger(
      id: ticketUuid,
      nomComplet: row['name'] as String? ?? 'Passager',
      siege: row['seatCode'] as String? ?? '—',
      statut: _statutOf(row['segmentStatus'] as String?),
      segmentOrigine: origin is Map ? '${origin['name']}' : '—',
      segmentDestination: destination is Map ? '${destination['name']}' : '—',
      ticketId: row['shortCode'] as String? ?? '',
      originStopUuid: origin is Map ? origin['uuid'] as String? : null,
      destinationStopUuid:
          destination is Map ? destination['uuid'] as String? : null,
      boardingEventUuid: row['boardingEventUuid'] as String?,
      // Champs de contrôle frontière (MEZ-94) : absents pour un départ
      // national, `null` si non renseignés côté réservation.
      nationalite: _nonBlank(row['nationality']),
      documentNumero: document is Map ? _nonBlank(document['number']) : null,
      // Identité de base (toutes lignes) et champs frontière
      // complémentaires (lignes internationales) — correction convoyeur.
      telephone: _nonBlank(row['phone']),
      prenom: _nonBlank(row['firstName']),
      nom: _nonBlank(row['lastName']),
      email: _nonBlank(row['email']),
      documentType: document is Map ? _nonBlank(document['type']) : null,
      dateNaissance: _nonBlank(row['birthDate']),
      fievreJauneVaccine: row['yellowFeverVaccinated'] is bool
          ? row['yellowFeverVaccinated'] as bool
          : null,
    );
  }

  /// `null` pour une valeur absente, non textuelle ou vide — une chaîne
  /// blanche ne doit jamais passer pour une information renseignée.
  static String? _nonBlank(Object? value) {
    if (value is! String) return null;
    final String trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }

  static List<Passenger> passengers(Object? body) {
    final rows = body is Map ? body['data'] : null;
    if (rows is! List) return const <Passenger>[];

    return rows
        .whereType<Map<dynamic, dynamic>>()
        .map((row) => passenger(Map<String, dynamic>.from(row)))
        .whereType<Passenger>()
        .toList();
  }

  /// Indicateur `international` posé à la racine de la réponse (MEZ-94) —
  /// `null` si le serveur ne le renvoie pas (version antérieure) : l'état
  /// est alors « inconnu », jamais assimilé à `false`.
  static bool? isInternational(Object? body) {
    if (body is! Map) return null;
    final Object? value = body['international'];
    return value is bool ? value : null;
  }

  static String? manifestVersion(Object? body) {
    if (body is! Map) return null;
    final version = body['manifestVersion'];
    return version?.toString();
  }

  /// `paid` et tout statut inconnu → `enAttente` (le passager reste à
  /// embarquer) ; `boarded` → `embarque` ; `cancelled` → `refuse` (vraie
  /// annulation) ; `no_show` → `refuse` (non présenté à l'embarquement,
  /// même rendu que `cancelled` — voir `StatutBadge`, qui ne distingue
  /// pas les deux) ; `early_drop_off` → `descenduAnticipe` (voir
  /// `ManifestSegmentStatus` côté backend, qui distingue désormais une
  /// vraie annulation d'une descente anticipée, toutes deux issues d'un
  /// segment "released").
  static BoardingStatus _statutOf(String? segmentStatus) =>
      switch (segmentStatus) {
        'boarded' => BoardingStatus.embarque,
        'cancelled' || 'no_show' => BoardingStatus.refuse,
        'early_drop_off' => BoardingStatus.descenduAnticipe,
        _ => BoardingStatus.enAttente,
      };
}
