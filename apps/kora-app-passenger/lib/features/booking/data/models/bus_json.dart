/// Couche DATA : lecture défensive du JSON de l'API Bus.
///
/// Les modèles de ce dossier ne font jamais de `as int` ou de `as String`
/// direct sur une réponse réseau : un champ absent, `null` ou renvoyé dans
/// un autre type (`"49"` au lieu de `49`) ferait tomber tout l'écran alors
/// qu'il ne manque qu'une information secondaire. Ces lecteurs retombent
/// sur une valeur neutre et laissent le modèle décider si c'est
/// acceptable.
///
/// Même principe que `AuthDtos` côté authentification — le domaine ne doit
/// jamais connaître les noms de champs de l'API (`km_from_origin`,
/// `available_seats_count`...), ils s'arrêtent ici.
abstract final class BusJson {
  /// Ouvre l'enveloppe `{ "data": [ … ] }` des réponses de liste.
  ///
  /// Tolère une réponse déjà « à plat » (un tableau nu) : une API qui
  /// cesserait d'envelopper ne doit pas vider l'écran.
  static List<Map<String, dynamic>> list(Object? body) {
    final raw = body is Map ? body['data'] : body;
    if (raw is! List) return const [];

    return [
      for (final item in raw)
        if (item is Map) Map<String, dynamic>.from(item),
    ];
  }

  /// Un objet imbriqué (`line`, `bus`, `origin_stop`...), ou une map vide
  /// s'il est absent.
  static Map<String, dynamic> map(Object? raw) =>
      raw is Map ? Map<String, dynamic>.from(raw) : const <String, dynamic>{};

  /// Une chaîne, ou [fallback] si le champ est absent, nul ou vide.
  static String string(Object? raw, [String fallback = '']) {
    if (raw == null) return fallback;
    final value = raw is String ? raw : '$raw';
    return value.isEmpty ? fallback : value;
  }

  /// Une chaîne facultative : `null` plutôt qu'une chaîne vide, pour que
  /// les champs optionnels du domaine (`City.station`...) restent nuls
  /// quand l'API ne les renseigne pas.
  static String? stringOrNull(Object? raw) {
    final value = string(raw);
    return value.isEmpty ? null : value;
  }

  /// Un entier. Accepte aussi bien `49` que `49.0` ou `"49"`.
  static int? intOrNull(Object? raw) => switch (raw) {
    final int value => value,
    final double value => value.round(),
    final String value =>
      int.tryParse(value) ?? double.tryParse(value)?.round(),
    _ => null,
  };

  /// Un décimal (`distance_km`, `km_from_origin`).
  static double? doubleOrNull(Object? raw) => switch (raw) {
    final double value => value,
    final int value => value.toDouble(),
    final String value => double.tryParse(value),
    _ => null,
  };

  /// Une liste de chaînes (`countries_crossed`).
  static List<String> strings(Object? raw) {
    if (raw is! List) return const [];
    return [
      for (final item in raw)
        if (item != null) '$item',
    ];
  }
}
