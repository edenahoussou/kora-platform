/// Le réseau Colis en dur : villes, hubs et grille tarifaire.
///
/// Rien de tout cela ne vient du serveur. `docs/mintlify/api/openapi.yaml`
/// ne publie aucun endpoint `parcel`, et le ticket l'interdit de toute
/// façon : aucune requête, aucun paiement réel.
///
/// Les valeurs ne sont pas inventées pour autant. L'ADR 0028 pose la
/// règle de routage — deux arrêts d'une même ligne, l'origine avant la
/// destination — et `docs/runbooks/colis-interurbain.md` donne la grille :
/// 1 500 XOF Cotonou–Parakou, 2 000 XOF Cotonou–Lomé, 6 000 XOF
/// Cotonou–Abidjan, 1 500 XOF par tranche de 5 kg au-delà, 30 kg au
/// maximum.
library;

/// Un point de dépôt ou de retrait : une agence KORA ou un point
/// partenaire, rattaché à un arrêt de ligne.
class ColisHub {
  const ColisHub({
    required this.id,
    required this.city,
    required this.name,
    required this.counter,
    required this.zone,
    required this.address,
    required this.phone,
    this.country = 'Bénin',
    this.weekdayHours = '07h00 - 20h00',
    this.weekendHours = '08h00 - 14h00',
  });

  final String id;

  /// La ville desservie — c'est elle que le voyageur cherche d'abord.
  final String city;

  /// Le nom du hub dans cette ville : une gare en porte parfois deux.
  final String name;

  /// Le guichet où le colis se dépose ou se retire.
  final String counter;

  /// Le quartier et le rang du hub dans le réseau — « Quartier Dantokpa
  /// · Hub Principal Sud ». C'est ce qui distingue deux gares d'une même
  /// ville au moment de choisir.
  final String zone;

  /// L'adresse où l'on se rend réellement, telle qu'on la dirait à un
  /// taxi.
  final String address;

  /// La ligne directe du guichet, pour appeler avant de se déplacer.
  final String phone;

  /// Le pays desservi : le réseau dépasse le Bénin, et une gare de Lomé
  /// ne se choisit pas par inadvertance.
  final String country;

  /// Les heures d'ouverture du comptoir. Un colis ne se retire pas à
  /// n'importe quelle heure, et c'est la première question qu'on se pose
  /// en lisant « prêt à retirer ».
  final String weekdayHours;
  final String weekendHours;

  /// « Cotonou · Gare Centrale », tel que la maquette l'écrit.
  String get label => '$city · $name';
}

/// Les hubs ouverts au dépôt comme au retrait.
const kColisHubs = <ColisHub>[
  ColisHub(
    id: 'ctn-gare-centrale',
    city: 'Cotonou',
    name: 'Gare Centrale',
    counter: 'Guichet Colis Dantokpa',
    zone: 'Quartier Dantokpa · Hub Principal Sud',
    address: 'Boulevard Steinmetz · Marché Dantokpa',
    phone: '+229 21 30 00 00',
    weekdayHours: '06h00 - 20h30',
    weekendHours: '08h00 - 14h00',
  ),
  ColisHub(
    id: 'ctn-etoile-rouge',
    city: 'Cotonou',
    name: 'Étoile Rouge',
    counter: 'Guichet Colis Étoile Rouge',
    zone: 'Place de l’Étoile Rouge · Hub Secondaire',
    address: 'Avenue de la Marina · Place de l’Étoile Rouge',
    phone: '+229 21 30 00 12',
    weekdayHours: '06h30 - 20h00',
    weekendHours: '08h00 - 13h00',
  ),
  ColisHub(
    id: 'pkx-gare-nord',
    city: 'Parakou',
    name: 'Hub Gare Nord',
    counter: 'Comptoir colis Gare Nord',
    zone: 'Avenue Albarika · Hub Central Nord',
    address: 'Avenue des Transports · Carrefour Gare Nord',
    phone: '+229 97 00 42 10',
    weekdayHours: '07h00 - 20h00',
    weekendHours: '08h00 - 14h00',
  ),
  ColisHub(
    id: 'boh-carrefour',
    city: 'Bohicon',
    name: 'Gare Carrefour',
    counter: 'Guichet Colis Carrefour',
    zone: 'RNIE2 Carrefour Zakpo · Hub Relais',
    address: 'RNIE2 · Carrefour Zakpo',
    phone: '+229 97 00 42 22',
    weekdayHours: '07h00 - 19h00',
    weekendHours: '08h00 - 13h00',
  ),
  ColisHub(
    id: 'pno-gare',
    city: 'Porto-Novo',
    name: 'Gare Ouando',
    counter: 'Guichet Colis Ouando',
    zone: 'Quartier Ouando · Hub Est',
    address: 'Route de Porto-Novo · Marché Ouando',
    phone: '+229 97 00 42 31',
    weekdayHours: '07h00 - 19h30',
    weekendHours: '08h00 - 13h00',
  ),
  ColisHub(
    id: 'djo-gare',
    city: 'Djougou',
    name: 'Gare Centrale',
    counter: 'Comptoir colis Djougou',
    zone: 'Quartier Zongo · Hub Nord-Ouest',
    address: 'Route de Djougou · Gare routière',
    phone: '+229 97 00 42 47',
    weekdayHours: '07h00 - 19h00',
    weekendHours: '08h00 - 13h00',
  ),
];

/// Les villes desservies, sans doublon : une ville porte parfois deux
/// hubs, et les pastilles de la maquette n'en montrent qu'une.
List<String> get kColisCities =>
    {for (final hub in kColisHubs) hub.city}.toList();

ColisHub colisHubById(String id) =>
    kColisHubs.firstWhere((hub) => hub.id == id);

/// Le tarif d'un corridor, hors tranches de poids.
///
/// Un corridor absent de cette table n'est pas desservi : l'ADR 0028
/// refuse toute substitution de hub, et proposer un prix pour une liaison
/// qui n'existe pas ferait venir un voyageur au guichet pour rien.
const _kCorridorBase = <String, int>{
  'Cotonou>Parakou': 1500,
  'Cotonou>Bohicon': 1000,
  'Cotonou>Porto-Novo': 1000,
  'Cotonou>Djougou': 2000,
  'Parakou>Cotonou': 1500,
  'Bohicon>Cotonou': 1000,
  'Porto-Novo>Cotonou': 1000,
  'Djougou>Cotonou': 2000,
  'Parakou>Bohicon': 1000,
  'Bohicon>Parakou': 1000,
};

/// Le poids maximal accepté, en kilogrammes (runbook).
const double kColisMaxWeightKg = 30;

/// Le pas du sélecteur de poids : la maquette affiche « 3,5 kg ».
const double kColisWeightStepKg = 0.5;

/// La tranche de poids incluse dans le tarif de base, en kilogrammes.
const double kColisIncludedWeightKg = 5;

/// Le supplément par tranche entamée au-delà de [kColisIncludedWeightKg].
const int kColisExtraPerBracket = 1500;

/// Le scellé sécurisé KORA, ajouté à chaque envoi.
const int kColisSealFee = 300;

/// Le transport pour un poids donné, hors scellé.
///
/// Les tranches se comptent **entamées** : 5,1 kg paie la même chose que
/// 10 kg. C'est la règle du runbook, et l'arrondir vers le bas ferait
/// encaisser moins que le tarif publié.
int colisTransportFee({
  required String originCity,
  required String destinationCity,
  required double weightKg,
}) {
  final base = _kCorridorBase['$originCity>$destinationCity'];
  if (base == null) return 0;

  final extra = weightKg <= kColisIncludedWeightKg
      ? 0
      : ((weightKg - kColisIncludedWeightKg) / kColisIncludedWeightKg).ceil();

  return base + extra * kColisExtraPerBracket;
}

/// Le total dû pour un envoi : transport et scellé.
int colisTotalFee({
  required String originCity,
  required String destinationCity,
  required double weightKg,
}) {
  final transport = colisTransportFee(
    originCity: originCity,
    destinationCity: destinationCity,
    weightKg: weightKg,
  );

  return transport == 0 ? 0 : transport + kColisSealFee;
}

/// Un corridor est-il desservi ? L'écran d'itinéraire s'en sert pour ne
/// pas laisser composer une liaison que l'agence refuserait.
bool colisCorridorExists(String originCity, String destinationCity) =>
    _kCorridorBase.containsKey('$originCity>$destinationCity');

/// Un poids tel qu'on l'écrit en français : « 3,5 », « 12 ».
///
/// Le point décimal de Dart passerait tel quel à l'écran, et « 3.5 kg »
/// sur un écran francophone se lit comme une faute.
String colisWeightText(double kg) {
  final rounded = (kg * 10).round() / 10;
  final whole = rounded == rounded.roundToDouble();

  return whole
      ? rounded.toStringAsFixed(0)
      : rounded.toStringAsFixed(1).replaceAll('.', ',');
}

/// Le prochain depart du corridor, et la mise a disposition qui
/// suit. Deux heures tirees de la grille de rotation, pas d'une
/// estimation faite dans l'app : le destinataire se deplace dessus.
const String kColisNextDeparture = '15h30';
const String kColisPlannedArrival = '08h00';

/// `#INC-AAAA-NNN` : le numero d'un dossier de reclamation.
///
/// Distinct de la reference du colis : un meme colis peut porter
/// plusieurs signalements, et c'est le dossier qu'on suit ensuite.
String colisNewIncidentNumber(DateTime now) {
  final suffix = (now.millisecondsSinceEpoch % 1000).toString().padLeft(3, '0');

  return '#INC-${now.year}-$suffix';
}

/// Le delai de garde gratuit au hub d'arrivee, et ce que coute
/// chaque jour au-dela.
///
/// Le colis n'attend pas indefiniment : passe ce delai, il repart
/// au hub de depart, et le destinataire doit le savoir en lisant
/// « pret a retirer ».
const int kColisFreeKeepingDays = 5;
const int kColisStorageFeePerDay = 500;

/// Depuis combien de minutes le suivi a ete relu.
///
/// Fige, faute de serveur : le jour ou le suivi viendra de l'API,
/// c'est l'horodatage de la reponse qui le donnera.
const int kColisRefreshedMinutes = 4;

/// L'assistance KORA, citee des qu'un ecran peut laisser le
/// voyageur sans recours : attente de paiement, retrait, litige.
const String kColisSupportPhone = '+229 21 30 00 00';

/// La duree pendant laquelle la demande de l'operateur reste
/// valable, en minutes.
const int kColisPaymentSessionMinutes = 10;

/// `TRX-AAAAMMJJ-NNNN` : l'identifiant de la tentative de paiement.
///
/// Distinct de la reference KC-, et cite bien avant elle : c'est lui
/// qu'on donne a l'assistance quand le paiement reste en attente,
/// quand la reference officielle n'existe pas encore.
String colisNewTransactionId(DateTime now) {
  final date =
      '${now.year}'
      '${now.month.toString().padLeft(2, '0')}'
      '${now.day.toString().padLeft(2, '0')}';
  final suffix = (now.millisecondsSinceEpoch % 10000).toString().padLeft(
    4,
    '0',
  );

  return 'TRX-$date-$suffix';
}

/// L'agent d'escale qui a pesé et scellé le colis.
///
/// Cité sur le devis : une pesée contestée se règle en nommant qui
/// l'a faite, et le ticket demande que le contrôle soit traçable.
const String kColisWeighingAgent = '#742';

/// Le solde KORA du compte de démonstration, en francs.
///
/// Il ne couvre pas un envoi entier : la maquette montre justement le
/// cas intéressant, celui où le Wallet entame le montant et l'opérateur
/// achève le reste.
const int kColisWalletBalance = 1000;

/// Un encaisseur proposé pour régler l'expédition.
///
/// Les mêmes que pour un billet, et dans le même ordre : le passager a
/// déjà payé un voyage par ce chemin-là, et lui présenter une liste
/// différente pour un colis lui ferait croire à un autre paiement.
class ColisGateway {
  const ColisGateway({
    required this.id,
    required this.name,
    required this.logo,
  });

  /// `fedapay`, `kkiapay` ou `card` — ce qui décide de l'écran suivant.
  final String id;

  final String name;

  /// Le logo de la passerelle, ou `null` pour la carte bancaire, qui
  /// n'appartient à personne et s'affiche sous un pictogramme.
  final String? logo;

  /// La carte bancaire se saisit dans la feuille de l'encaisseur, le
  /// Mobile Money de FedaPay se pousse sur le téléphone : ce n'est pas
  /// le même écran qui suit.
  bool get isCard => id == card;

  /// KKiaPay encaisse dans sa propre feuille ; FedaPay, lui, réclame
  /// l'opérateur et le numéro d'avance.
  bool get collectsInSheet => id != fedapay;

  static const String fedapay = 'fedapay';
  static const String kkiapay = 'kkiapay';
  static const String card = 'card';
}

/// Les encaisseurs, dans l'ordre du parcours billet.
const kColisGateways = <ColisGateway>[
  ColisGateway(
    id: ColisGateway.fedapay,
    name: 'FedaPay',
    logo: 'assets/images/fedapay.jpg',
  ),
  ColisGateway(
    id: ColisGateway.kkiapay,
    name: 'KKiaPay',
    logo: 'assets/images/kkiapay.png',
  ),
  // Pas de carte bancaire : l'expédition se règle en Mobile Money, et
  // une troisième ligne sans logo faisait nombre sans rien offrir de
  // plus. L'écran de saisie du prestataire reste en place — il sert
  // encore au billet.
];

/// L'encaisseur portant [id], ou le premier de la liste.
ColisGateway colisGatewayOf(String? id) {
  for (final gateway in kColisGateways) {
    if (gateway.id == id) return gateway;
  }
  return kColisGateways.first;
}

/// Les réseaux Mobile Money que FedaPay sait débiter au Bénin.
///
/// Écrits ici parce qu'aucun catalogue serveur ne couvre le colis. Le
/// jour où `GET /payments/methods` le servira, cette liste disparaît —
/// c'est le serveur qui sait lesquels encaissent aujourd'hui.
const kColisMomoNetworks = <String>['mtn_momo', 'moov_flooz', 'celtiis_cash'];

/// `KC-AAAAMMJJ-NNNNN` : la référence officielle, émise au paiement.
///
/// Son suffixe vient de l'heure, faute de serveur pour le délivrer. Le
/// jour où l'API existe, c'est elle qui la donnera — et cette fonction
/// disparaîtra, car une référence inventée par le client n'en est pas
/// une.
String colisNewReference(DateTime now) {
  final date =
      '${now.year}'
      '${now.month.toString().padLeft(2, '0')}'
      '${now.day.toString().padLeft(2, '0')}';
  final suffix = (now.millisecondsSinceEpoch % 100000).toString().padLeft(
    5,
    '0',
  );

  return 'KC-$date-$suffix';
}
