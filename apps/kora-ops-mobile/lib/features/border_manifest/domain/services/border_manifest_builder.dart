import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../printing/domain/entities/border_manifest_payload.dart';

/// Ce dont on a besoin pour préparer un manifeste frontière : le départ
/// sélectionné et le manifeste tel qu'il est chargé (`ManifesteState`).
class BorderManifestInput {
  const BorderManifestInput({
    required this.depart,
    required this.passengers,
    required this.isInternational,
    this.manifestVersion,
    this.fetchedAt,
  });

  final Depart depart;
  final List<Passenger> passengers;

  /// `null` = inconnu (cache antérieur à MEZ-94) — l'impression est alors
  /// refusée avec une demande de rafraîchissement.
  final bool? isInternational;

  final String? manifestVersion;
  final DateTime? fetchedAt;
}

/// Résultat de la préparation : soit un [payload] prêt à imprimer, soit la
/// liste des raisons du refus ([issues], en français, affichables telles
/// quelles).
class BorderManifestBuild {
  const BorderManifestBuild.ready(BorderManifestPayload this.payload)
      : issues = const <String>[];

  const BorderManifestBuild.refused(this.issues) : payload = null;

  final BorderManifestPayload? payload;
  final List<String> issues;

  bool get isReady => payload != null;
}

/// Construit le [BorderManifestPayload] d'un départ et **refuse** de le
/// faire si le manifeste n'est pas complet (ticket MEZ-94 : « refuser
/// l'impression si le manifeste n'est pas complet ou si le contrat serveur
/// signale une information obligatoire manquante »).
///
/// Miroir exact de la règle serveur (`GenerateBorderManifestPdfAction`,
/// 422 `manifest_incomplete`) : pour chaque passager, nom, nationalité,
/// n° de document, siège et destination sont obligatoires. Le serveur
/// n'envoie ces informations qu'à la ligne internationale et les laisse
/// `null` quand elles manquent — c'est ce contrat qui est ici vérifié.
///
/// Sans montant : ni le payload ni les passagers n'en portent.
abstract final class BorderManifestBuilder {
  /// Nom substitué par `ManifesteDtos` quand le serveur n'en renvoie pas.
  static const String _placeholderName = 'Passager';

  /// Valeur substituée par `ManifesteDtos` pour un siège/arrêt inconnu.
  static const String _placeholder = '—';

  /// Un passager compte sur le manifeste frontière tant qu'il n'est ni
  /// refusé (annulé, no-show) ni descendu avant la frontière.
  static bool isTravelling(Passenger passenger) =>
      passenger.statut != BoardingStatus.refuse &&
      passenger.statut != BoardingStatus.descenduAnticipe;

  static BorderManifestBuild build(
    BorderManifestInput input, {
    DateTime? now,
  }) {
    final List<String> issues = <String>[];

    if (input.isInternational == null) {
      issues.add(
        'Le type de départ (national ou international) est inconnu. '
        'Rafraîchissez le manifeste puis réessayez.',
      );
    } else if (!input.isInternational!) {
      issues.add(
        'Ce départ n\'est pas international : pas de manifeste frontière.',
      );
    }

    final List<Passenger> travelling =
        input.passengers.where(isTravelling).toList();

    if (travelling.isEmpty) {
      issues.add('Aucun passager à faire figurer sur le manifeste.');
    }

    for (final Passenger passenger in travelling) {
      final List<String> missing = missingFields(passenger);
      if (missing.isNotEmpty) {
        final String name = _isBlank(passenger.nomComplet) ||
                passenger.nomComplet.trim() == _placeholderName
            ? 'Passager sans nom'
            : passenger.nomComplet.trim();
        final String seat =
            _isMissing(passenger.siege) ? '—' : passenger.siege.trim();
        issues.add('$name ($seat) : manque ${missing.join(', ')}');
      }
    }

    if (issues.isNotEmpty) return BorderManifestBuild.refused(issues);

    final Depart depart = input.depart;
    final DateTime printedAt = now ?? DateTime.now();

    return BorderManifestBuild.ready(
      BorderManifestPayload(
        departureNumber: departureNumber(depart.id),
        departureDate: depart.date,
        departureTime: depart.heure,
        lineCode: depart.lineCode ?? depart.lineName ?? '—',
        route: depart.route,
        passengers: travelling
            .map((Passenger p) => BorderManifestPassengerLine(
                  nomComplet: p.nomComplet.trim(),
                  nationalite: p.nationalite!.trim(),
                  documentNumero: p.documentNumero!.trim(),
                  destination: p.segmentDestination.trim(),
                  siege: p.siege.trim(),
                ))
            .toList(),
        printedAt: printedAt,
        manifestVersion: input.manifestVersion,
        manifestFetchedAt: input.fetchedAt,
      ),
    );
  }

  /// Champs obligatoires manquants pour ce passager (libellés français).
  /// Liste vide = passager complet.
  static List<String> missingFields(Passenger passenger) => <String>[
        if (_isBlank(passenger.nomComplet) ||
            passenger.nomComplet.trim() == _placeholderName)
          'nom',
        if (_isBlank(passenger.nationalite)) 'nationalité',
        if (_isBlank(passenger.documentNumero)) 'n° de document',
        if (_isMissing(passenger.siege)) 'siège',
        if (_isMissing(passenger.segmentDestination)) 'destination',
      ];

  /// Numéro de départ imprimé : 8 premiers caractères de l'UUID, en
  /// majuscules (l'UUID complet ne tient pas sur 32 caractères).
  static String departureNumber(String departureUuid) {
    final String compact = departureUuid.replaceAll('-', '').toUpperCase();
    return compact.length <= 8 ? compact : compact.substring(0, 8);
  }

  static bool _isBlank(String? value) => value == null || value.trim().isEmpty;

  static bool _isMissing(String? value) =>
      _isBlank(value) || value!.trim() == _placeholder;
}
