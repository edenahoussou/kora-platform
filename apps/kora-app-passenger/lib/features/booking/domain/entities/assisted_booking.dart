import 'package:equatable/equatable.dart';

import 'price.dart';

/// L'agence qui a pris le dossier en charge.
///
/// Le voyageur ne la choisit plus : il s'adresse au support central de
/// KORA, et c'est le réseau qui affecte l'agence — elle reste donc
/// inconnue tant que personne n'a pris le dossier.
class AssistanceAgency extends Equatable {
  const AssistanceAgency({
    required this.id,
    required this.name,
    this.code,
    this.city,
  });

  final String id;
  final String name;

  /// Le code métier (`AG-000001`), jamais l'identifiant numérique.
  final String? code;
  final String? city;

  @override
  List<Object?> get props => [id, name, code, city];
}

/// Les numéros du support central KORA, tels que le dossier les rend
/// (`channels`) : c'est par eux que le voyageur joint un agent, quelle que
/// soit l'agence qui finira par traiter la demande.
///
/// Un canal que le serveur n'expose pas vaut `null` : l'écran n'en propose
/// alors pas la ligne plutôt que d'ouvrir un lien vide.
class AssistanceSupport extends Equatable {
  const AssistanceSupport({
    this.callPhone,
    this.whatsappPhone,
    this.callUrl,
    this.whatsappUrl,
  });

  final String? callPhone;
  final String? whatsappPhone;

  /// `tel:` et `wa.me`, déjà composés par le serveur — le message WhatsApp
  /// y est inclus.
  final String? callUrl;
  final String? whatsappUrl;

  @override
  List<Object?> get props => [callPhone, whatsappPhone, callUrl, whatsappUrl];
}

/// Le canal par lequel le voyageur joint le support KORA.
enum AssistanceChannel {
  whatsapp('whatsapp'),
  call('call');

  const AssistanceChannel(this.wire);

  final String wire;
}

/// Où en est le dossier côté agence.
///
/// [unknown] couvre un statut que le serveur aurait ajouté depuis : on
/// l'affiche comme « en cours de traitement » plutôt que de planter.
enum AssistanceStatus {
  pending,
  inProgress,
  finalized,
  refused,
  unavailable,
  expired,
  cancelled,

  /// Le voyageur a repris la réservation pour la payer en ligne : le
  /// serveur clôt le dossier (`cancelled`), mais la réservation, elle, vit
  /// toujours.
  resumedOnline,
  unknown;

  /// [reservationStatus] départage un dossier `cancelled` : annulé pour de
  /// bon, ou repris en ligne sur une réservation toujours vivante.
  static AssistanceStatus fromWire(String? wire, {String? reservationStatus}) =>
      switch (wire) {
        'cancelled' when _alive.contains(reservationStatus) => resumedOnline,
        _ => _fromWire(wire),
      };

  static const _alive = {'held', 'confirmed', 'paid', 'ticketed', 'boarded'};

  static AssistanceStatus _fromWire(String? wire) => switch (wire) {
    'pending' => pending,
    'in_progress' => inProgress,
    'finalized' => finalized,
    'refused' => refused,
    'unavailable' => unavailable,
    'expired' => expired,
    'cancelled' => cancelled,
    _ => unknown,
  };

  /// Le dossier ne bougera plus : il n'y a plus rien à attendre.
  bool get isClosed => switch (this) {
    finalized ||
    refused ||
    unavailable ||
    expired ||
    cancelled ||
    resumedOnline => true,
    pending || inProgress || unknown => false,
  };

  /// Clos sans aboutir : il faut proposer une nouvelle réservation.
  bool get isDeadEnd => isClosed && this != finalized && this != resumedOnline;
}

/// Un tronçon du voyage, tel que l'agence le verra.
class AssistanceLeg extends Equatable {
  const AssistanceLeg({
    required this.origin,
    required this.destination,
    this.departsAt,
  });

  final String origin;
  final String destination;
  final DateTime? departsAt;

  @override
  List<Object?> get props => [origin, destination, departsAt];
}

/// Le dossier d'assistance d'une réservation, tel que le rend
/// `GET bookings/{uuid}/assistance`.
///
/// **La réservation reste la même** : [reservationId] et [reference] la
/// suivent de l'envoi à la finalisation, que ce soit l'agence ou le
/// voyageur qui la termine.
class AssistedBooking extends Equatable {
  const AssistedBooking({
    required this.id,
    required this.status,
    required this.reservationId,
    required this.reference,
    required this.amount,
    this.agency,
    this.support = const AssistanceSupport(),
    this.channel = AssistanceChannel.whatsapp,
    this.validUntil,
    this.whatsappMessage,
    this.legs = const [],
    this.seatLabels = const [],
    this.passengerCount = 1,
    this.canResumeOnline = false,
  });

  final String id;
  final AssistanceStatus status;
  final String reservationId;

  /// `KBR-XXXXXXXX` : le fil rouge entre le voyageur et l'agence.
  final String reference;

  /// `null` tant que le réseau n'a affecté personne : la demande attend
  /// dans la file, et l'écran le dit au lieu d'inventer une agence.
  final AssistanceAgency? agency;

  /// Par où joindre KORA, maintenant.
  final AssistanceSupport support;

  final AssistanceChannel channel;

  /// Indicatif : l'agence recalcule le prix en prenant le dossier.
  final Price amount;

  /// Au-delà, le dossier expire de lui-même.
  final DateTime? validUntil;

  /// Le message que le serveur a rédigé : déjà glissé dans l'URL WhatsApp,
  /// et copiable pour l'envoyer autrement.
  final String? whatsappMessage;

  final List<AssistanceLeg> legs;

  /// Les sièges souhaités — pas bloqués pendant l'attente.
  final List<String> seatLabels;
  final int passengerCount;

  /// Tant qu'aucun agent n'a pris le dossier, le voyageur peut revenir au
  /// paiement en ligne.
  final bool canResumeOnline;

  @override
  List<Object?> get props => [
    id,
    status,
    reservationId,
    reference,
    agency,
    support,
    channel,
    amount,
    validUntil,
    whatsappMessage,
    legs,
    seatLabels,
    passengerCount,
    canResumeOnline,
  ];
}

/// Un siège demandé, pour un voyageur et un tronçon.
///
/// Gardé sur le téléphone parce que le dossier ne rend que les numéros :
/// reprendre le paiement en ligne exige l'identifiant de chaque siège.
class AssistanceSeat extends Equatable {
  const AssistanceSeat({
    required this.passengerIndex,
    required this.legOrder,
    required this.seatId,
    required this.label,
  });

  final int passengerIndex;
  final int legOrder;
  final String seatId;
  final String label;

  @override
  List<Object?> get props => [passengerIndex, legOrder, seatId, label];
}

/// La trace, sur le téléphone, d'une réservation confiée à une agence.
///
/// L'API ne liste pas les dossiers d'un voyageur : sans cette trace, il ne
/// retrouverait sa demande qu'en gardant l'écran de confirmation ouvert.
class AssistanceRecord extends Equatable {
  const AssistanceRecord({
    required this.reservationId,
    required this.reference,
    required this.sentAt,
    required this.origin,
    required this.destination,
    required this.seats,
    this.departsAt,
    this.agencyName,
  });

  final String reservationId;
  final String reference;
  final DateTime sentAt;
  final String origin;
  final String destination;
  final DateTime? departsAt;
  final String? agencyName;
  final List<AssistanceSeat> seats;

  int get passengerCount =>
      seats.map((seat) => seat.passengerIndex).toSet().length;

  @override
  List<Object?> get props => [
    reservationId,
    reference,
    sentAt,
    origin,
    destination,
    departsAt,
    agencyName,
    seats,
  ];
}
