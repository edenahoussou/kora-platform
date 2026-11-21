/// Les données figées du parcours Colis, le temps que l'API existe.
///
/// `docs/mintlify/api/openapi.yaml` ne publie aucun endpoint `parcel` : le
/// socle serveur est posé (ADR 0028) mais le contrat mobile, lui, n'est pas
/// écrit. Les écrans se construisent donc sur ces valeurs, et le ticket le
/// demande explicitement — aucune requête réseau, aucun paiement réel.
///
/// Les montants suivent le runbook `docs/runbooks/colis-interurbain.md` :
/// 1 500 XOF Cotonou–Parakou, 2 000 XOF Cotonou–Lomé, 6 000 XOF
/// Cotonou–Abidjan, puis 1 500 XOF par tranche de 5 kg au-delà, 30 kg au
/// maximum. Les inventer autrement donnerait une maquette qui ment sur le
/// prix.
library;

/// Où en est un envoi, de l'étiquette posée au retrait.
enum ColisStepState {
  /// L'étape est passée : horodatée, elle ne bougera plus.
  done,

  /// L'étape en cours : c'est elle que la carte met en avant.
  current,

  /// À venir : son heure n'est qu'une prévision.
  planned,
}

/// Une étape de l'acheminement, telle que la carte de suivi la montre.
class ColisStep {
  const ColisStep({required this.state, required this.time, this.hubCity});

  final ColisStepState state;

  /// L'heure observée pour une étape passée, prévue pour les autres.
  final String time;

  /// La ville du hub, pour l'étape d'arrivée qui la nomme.
  final String? hubCity;
}

/// Un envoi en cours, vu depuis l'accueil de l'onglet Colis.
class ColisShipment {
  const ColisShipment({
    required this.reference,
    required this.originCity,
    required this.originHub,
    required this.destinationCity,
    required this.destinationHub,
    required this.line,
    required this.lastUpdateTime,
    required this.etaTime,
    required this.steps,
  });

  /// `KC-AAAAMMJJ-NNNNN` : la référence de suivi, lisible et dictable au
  /// téléphone.
  final String reference;

  final String originCity;
  final String originHub;
  final String destinationCity;
  final String destinationHub;

  /// La ligne Bus qui porte le colis — un envoi voyage en soute d'un
  /// départ, jamais seul (ADR 0028).
  final String line;

  final String lastUpdateTime;
  final String etaTime;

  /// Dépôt, route, arrivée : les trois étapes de la carte, dans l'ordre.
  final List<ColisStep> steps;
}

/// L'envoi en cours de démonstration : Cotonou → Parakou, le corridor que
/// le runbook donne en premier.
const kMockColisShipments = <ColisShipment>[
  ColisShipment(
    reference: 'KC-20261119-08421',
    originCity: 'Cotonou',
    originHub: 'Gare Centrale',
    destinationCity: 'Parakou',
    destinationHub: 'Hub Nord',
    line: 'A1',
    lastUpdateTime: '14:15',
    etaTime: '16:30',
    steps: [
      ColisStep(state: ColisStepState.done, time: '13:40'),
      ColisStep(state: ColisStepState.current, time: '14:05'),
      ColisStep(
        state: ColisStepState.planned,
        time: '15:30',
        hubCity: 'Parakou',
      ),
    ],
  ),
];
