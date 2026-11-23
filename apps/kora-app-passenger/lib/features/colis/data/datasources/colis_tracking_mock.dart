/// Le suivi d'un envoi, figé : jalons, guichet de remise, horaires.
///
/// Même règle que le reste du module : aucun endpoint `parcel` n'existe
/// dans l'OpenAPI mobile, et le ticket interdit toute requête. Ces
/// valeurs décrivent ce que l'écran doit savoir afficher, pas ce que le
/// serveur dira.
library;

/// Où en est un envoi, du scellé au retrait.
enum ColisTrackingStatus {
  /// Scellé au hub de départ, pas encore chargé.
  sealed,

  /// En soute, sur la route.
  inTransit,

  /// Arrivé au hub de destination, en attente du destinataire.
  readyForPickup,

  /// Remis contre code et pièce d'identité.
  collected,
}

/// Un jalon d'acheminement : ce qui s'est passé, et quand.
class ColisMilestone {
  const ColisMilestone({
    required this.titleKey,
    required this.time,
    required this.detail,
    required this.done,
  });

  /// La clé de traduction du jalon : son libellé ne dépend pas du colis.
  final String titleKey;

  /// L'heure observée pour un jalon passé, prévue pour les autres.
  final String time;

  /// Ce que le jalon précise : le hub, le poids certifié, le convoi.
  final String detail;

  final bool done;
}

/// Le guichet où le destinataire retire son colis.
class ColisPickupCounter {
  const ColisPickupCounter({
    required this.hub,
    required this.address,
    required this.weekdayHours,
    required this.weekendHours,
  });

  final String hub;
  final String address;
  final String weekdayHours;
  final String weekendHours;
}

/// Un envoi suivi, tel que la liste et le détail le montrent.
class ColisTrackedParcel {
  const ColisTrackedParcel({
    required this.reference,
    required this.status,
    required this.originCity,
    required this.originHub,
    required this.destinationCity,
    required this.destinationHub,
    required this.line,
    required this.etaTime,
    required this.certifiedWeightKg,
    required this.milestones,
    required this.counter,
    required this.outgoing,
    required this.senderName,
    required this.recipientName,
    required this.recipientPhoneMasked,
    required this.droppedAtTime,
    required this.busNumber,
    required this.sealNumber,
    this.checkpoint,
    this.collectedOn,
    this.receivedOn,
    this.keepUntil,
  });

  final String reference;
  final ColisTrackingStatus status;

  final String originCity;
  final String originHub;
  final String destinationCity;
  final String destinationHub;

  final String line;
  final String etaTime;
  final double certifiedWeightKg;

  /// Les cinq jalons du parcours, dans l'ordre.
  final List<ColisMilestone> milestones;

  final ColisPickupCounter counter;

  /// `true` si l'utilisateur est l'expéditeur, `false` s'il attend le
  /// colis : la liste sépare les deux, et on ne retire pas ce qu'on
  /// envoie.
  final bool outgoing;

  /// Qui a déposé, et qui viendra retirer. La liste montre le
  /// destinataire : c'est à lui qu'on pense en cherchant un envoi.
  final String senderName;
  final String recipientName;

  /// Le numéro du destinataire, masqué — `+229 95 •• •• 88`.
  ///
  /// Jamais en clair : le suivi se partage, et un numéro lisible sur une
  /// capture d'écran suffit à usurper un retrait.
  final String recipientPhoneMasked;

  /// L'heure du dépôt au guichet de départ.
  final String droppedAtTime;

  /// Le convoi qui porte le colis — « #408 ». C'est ce qu'on cite au
  /// guichet de régulation quand on cherche un car.
  final String busNumber;

  /// Le scellé de sûreté posé au dépôt, vérifié à l'arrivée.
  final String sealNumber;

  /// Le dernier poste de contrôle franchi, quand le colis roule.
  final String? checkpoint;

  /// La date du retrait effectif, pour un colis déjà remis.
  final String? collectedOn;

  /// La date d'arrivée au hub, et la limite de garde gratuite.
  final String? receivedOn;
  final String? keepUntil;

  /// Le rang du dernier jalon franchi, à partir de 1.
  int get step => milestones.where((milestone) => milestone.done).length;

  int get totalSteps => milestones.length;
}

const _kDefaultCounter = ColisPickupCounter(
  hub: 'Hub Parakou Gare Nord',
  address: 'Avenue des Transports, Carrefour Gare Nord',
  weekdayHours: '07h00 – 20h00',
  weekendHours: '08h00 – 14h00',
);

/// Les envois suivis de démonstration : un en route, un à retirer.
final kColisTrackedParcels = <ColisTrackedParcel>[
  ColisTrackedParcel(
    reference: 'KC-20261119-08421',
    status: ColisTrackingStatus.inTransit,
    originCity: 'Cotonou',
    originHub: 'Gare Centrale',
    destinationCity: 'Parakou',
    destinationHub: 'Hub Gare Nord',
    line: 'RN1',
    etaTime: '16:30',
    certifiedWeightKg: 4.2,
    outgoing: true,
    counter: _kDefaultCounter,
    senderName: 'Koffi MENSAH',
    recipientName: 'Amina BIO',
    recipientPhoneMasked: '+229 95 •• •• 88',
    droppedAtTime: '13:40',
    busNumber: '#408',
    sealNumber: '#SC-9842-BJ',
    checkpoint: 'Dassa-Zoumè (15:22)',
    milestones: const [
      ColisMilestone(
        titleKey: 'colis_milestone_sealed',
        time: '13:40',
        detail: 'Cotonou · Gare Centrale — poids certifié 4,2 kg',
        done: true,
      ),
      ColisMilestone(
        titleKey: 'colis_milestone_loaded',
        time: '14:10',
        detail: 'Départ effectif 14:15 · convoi KORA ligne RN1',
        done: true,
      ),
      ColisMilestone(
        titleKey: 'colis_milestone_transit',
        time: '14:15',
        detail: 'Corridor interurbain RN1',
        done: true,
      ),
      ColisMilestone(
        titleKey: 'colis_milestone_arrived',
        time: '16:30',
        detail: 'Parakou · Hub Gare Nord',
        done: false,
      ),
      ColisMilestone(
        titleKey: 'colis_milestone_collected',
        time: '—',
        detail: 'Contre code de retrait et pièce d’identité',
        done: false,
      ),
    ],
  ),
  ColisTrackedParcel(
    reference: 'KC-20261118-07310',
    status: ColisTrackingStatus.readyForPickup,
    originCity: 'Bohicon',
    originHub: 'Gare Carrefour',
    destinationCity: 'Parakou',
    destinationHub: 'Hub Gare Nord',
    line: 'RN2',
    etaTime: '09:20',
    certifiedWeightKg: 2.5,
    outgoing: false,
    counter: _kDefaultCounter,
    senderName: 'Rachida SALAMI',
    recipientName: 'Koffi MENSAH',
    recipientPhoneMasked: '+229 97 •• •• 45',
    droppedAtTime: '06:40',
    busNumber: '#212',
    sealNumber: '#SC-9771-BJ',
    receivedOn: '19 nov.',
    keepUntil: '24 nov. (18h00)',
    milestones: const [
      ColisMilestone(
        titleKey: 'colis_milestone_sealed',
        time: '06:40',
        detail: 'Bohicon · Gare Carrefour — poids certifié 2,5 kg',
        done: true,
      ),
      ColisMilestone(
        titleKey: 'colis_milestone_loaded',
        time: '07:05',
        detail: 'Départ effectif 07:10 · convoi KORA ligne RN2',
        done: true,
      ),
      ColisMilestone(
        titleKey: 'colis_milestone_transit',
        time: '07:10',
        detail: 'Corridor interurbain RN2',
        done: true,
      ),
      ColisMilestone(
        titleKey: 'colis_milestone_arrived',
        time: '09:20',
        detail: 'Parakou · Hub Gare Nord',
        done: true,
      ),
      ColisMilestone(
        titleKey: 'colis_milestone_collected',
        time: '—',
        detail: 'Contre code de retrait et pièce d’identité',
        done: false,
      ),
    ],
  ),
  ColisTrackedParcel(
    reference: 'KC-20261102-04192',
    status: ColisTrackingStatus.collected,
    originCity: 'Porto-Novo',
    originHub: 'Gare Ouando',
    destinationCity: 'Parakou',
    destinationHub: 'Hub Gare Nord',
    line: 'RN3',
    etaTime: '11:40',
    certifiedWeightKg: 1.8,
    outgoing: true,
    counter: _kDefaultCounter,
    senderName: 'Koffi MENSAH',
    recipientName: 'Sèdjro AGBO',
    recipientPhoneMasked: '+229 96 •• •• 03',
    droppedAtTime: '07:15',
    busNumber: '#118',
    sealNumber: '#SC-9510-BJ',
    collectedOn: '02 nov. 2026',
    receivedOn: '01 nov.',
    milestones: const [
      ColisMilestone(
        titleKey: 'colis_milestone_sealed',
        time: '07:15',
        detail: 'Porto-Novo · Gare Ouando — poids certifié 1,8 kg',
        done: true,
      ),
      ColisMilestone(
        titleKey: 'colis_milestone_loaded',
        time: '07:50',
        detail: 'Départ effectif 08:00 · convoi KORA ligne RN3',
        done: true,
      ),
      ColisMilestone(
        titleKey: 'colis_milestone_transit',
        time: '08:00',
        detail: 'Corridor interurbain RN3',
        done: true,
      ),
      ColisMilestone(
        titleKey: 'colis_milestone_arrived',
        time: '11:40',
        detail: 'Parakou · Hub Gare Nord',
        done: true,
      ),
      ColisMilestone(
        titleKey: 'colis_milestone_collected',
        time: '09:05',
        detail: 'Remis contre code de retrait et pièce d’identité',
        done: true,
      ),
    ],
  ),
];

/// Un pré-enregistrement non finalisé : saisi dans l'app, pas encore
/// déposé au guichet.
///
/// Il n'a pas de référence officielle — seulement un numéro de
/// brouillon, qui évite de refaire la saisie au comptoir.
class ColisDraftParcel {
  const ColisDraftParcel({
    required this.draftNumber,
    required this.originCity,
    required this.originHub,
    required this.destinationCity,
    required this.destinationHub,
    required this.indicativeFee,
  });

  final String draftNumber;
  final String originCity;
  final String originHub;
  final String destinationCity;
  final String destinationHub;

  /// Le devis indicatif, en francs : ce que le guichet confirmera après
  /// pesée.
  final int indicativeFee;
}

const kColisDraftParcels = <ColisDraftParcel>[
  ColisDraftParcel(
    draftNumber: '#PR-892',
    originCity: 'Cotonou',
    originHub: 'Agence Dantokpa',
    destinationCity: 'Bohicon',
    destinationHub: 'Gare Carrefour',
    indicativeFee: 2500,
  ),
];

ColisTrackedParcel colisParcelByReference(String reference) =>
    kColisTrackedParcels.firstWhere(
      (parcel) => parcel.reference == reference,
      orElse: () => kColisTrackedParcels.first,
    );
