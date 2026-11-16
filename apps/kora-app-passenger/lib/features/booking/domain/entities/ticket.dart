import 'package:equatable/equatable.dart';

import 'departure.dart';
import 'seat.dart';

/// Où en est un billet — les trois onglets de l'écran « Mes voyages ».
enum TicketStatus {
  /// Payé, départ à venir. Le billet est utilisable.
  confirmed,

  /// Le voyage a eu lieu. Conservé pour l'historique et le justificatif.
  completed,

  /// Annulé, par le voyageur ou par l'opérateur.
  cancelled,
}

/// Un bus du voyage, tel que le billet le décrit.
///
/// Un billet de correspondance en porte autant qu'il y a de bus à
/// prendre : le serveur les rend ordonnés, chacun avec ses villes, son
/// siège et ses heures. Les taire laisserait le voyageur devant un
/// billet « Cotonou → Natitingou » sans savoir qu'il descend à Bohicon.
class TicketLeg extends Equatable {
  const TicketLeg({
    required this.order,
    required this.origin,
    required this.destination,
    this.seatCode = '',
    this.departsAt,
    this.arrivesAt,
    this.isReturn = false,
  });

  /// Le rang du tronçon, à partir de 1 — l'ordre dans lequel on prend
  /// les bus.
  final int order;

  final String origin;
  final String destination;

  /// La place retenue sur **ce** bus : elle change d'un tronçon à
  /// l'autre.
  final String seatCode;

  /// Heures de l'arrêt acheté, ramenées à l'heure du téléphone. Nulles
  /// quand le serveur n'a pas d'horaire pour cet arrêt.
  final DateTime? departsAt;
  final DateTime? arrivesAt;

  /// Le tronçon appartient au retour d'un aller-retour.
  final bool isReturn;

  @override
  List<Object?> get props => [
    order,
    origin,
    destination,
    seatCode,
    departsAt,
    arrivesAt,
    isReturn,
  ];
}

/// Couche DOMAIN : un billet acheté, tel que le liste l'onglet Voyages.
///
/// Va plus loin qu'[UpcomingTrip], qui ne sert qu'à la carte de l'accueil :
/// un billet porte sa référence — c'est elle qu'on présente au contrôle —
/// et son statut, puisque la liste montre aussi les voyages passés et
/// annulés.
class Ticket extends Equatable {
  const Ticket({
    required this.reference,
    required this.departure,
    required this.seat,
    required this.status,
    this.reservationId,
    this.qrCode = '',
    this.ticketIds = const [],
    this.legs = const [],
    this.arrivesAt,
  });

  /// Référence imprimée sur le billet : `KE-20260804-78342`, ou le code
  /// court du serveur, `K5F414H5`.
  final String reference;

  /// La réservation qui porte le billet. `null` pour un billet de
  /// démonstration ; c'est elle qui mène à la liste des billets émis,
  /// voyageur par voyageur.
  final String? reservationId;

  /// La charge signée (Ed25519) que la douchette du convoyeur vérifie hors
  /// ligne. Vide pour un billet de démonstration.
  final String qrCode;

  /// Les UUID des billets émis pour ce voyage, un par voyageur. C'est par
  /// eux — jamais par un identifiant numérique — que se télécharge le PDF
  /// officiel.
  final List<String> ticketIds;

  final Departure departure;
  final Seat seat;
  final TicketStatus status;

  /// Les bus du voyage, dans l'ordre. Vide ou à un seul élément pour un
  /// trajet direct — c'est [isConnecting] qui tranche.
  final List<TicketLeg> legs;

  /// L'arrivée au dernier arrêt acheté, telle que le serveur la donne.
  /// `null` quand il n'a pas d'horaire pour cet arrêt.
  final DateTime? arrivesAt;

  /// Le voyage impose au moins un changement de bus à l'aller.
  bool get isConnecting => legs.where((leg) => !leg.isReturn).length > 1;

  /// Le billet couvre aussi le retour : un seul billet, deux sens.
  bool get isRoundTrip => legs.any((leg) => leg.isReturn);

  /// Le nombre de changements de bus à l'aller.
  int get connectionsCount {
    final outbound = legs.where((leg) => !leg.isReturn).length;
    return outbound > 1 ? outbound - 1 : 0;
  }

  /// Les villes où l'on change de bus, dans l'ordre.
  List<String> get connectionCities => [
    for (final leg in legs.where((leg) => !leg.isReturn).skip(1)) leg.origin,
  ];

  /// `KE-…78342` : la maquette n'affiche que la fin de la référence dans
  /// la liste. Elle suffit à reconnaître son billet, et la référence
  /// complète reste sur l'écran de détail.
  String get shortReference {
    const visible = 5;
    // Les codes courts du serveur (`K5F414H5`) se lisent en entier : les
    // tronquer sous un préfixe `KE-` inventé les rendait méconnaissables.
    if (reference.length <= 10) return reference;

    return '${reference.substring(0, 3)}…'
        '${reference.substring(reference.length - visible)}';
  }

  /// Nombre de jours entiers avant le départ, à partir de [now].
  ///
  /// Comparé au jour près et non à l'instant près : un départ demain à
  /// 07:30 reste « demain » même s'il est 23:00 ce soir, soit moins de
  /// 24 heures plus tard.
  int daysUntilDeparture(DateTime now) {
    final departureDay = DateTime(
      departure.departsAt.year,
      departure.departsAt.month,
      departure.departsAt.day,
    );
    final today = DateTime(now.year, now.month, now.day);

    return departureDay.difference(today).inDays;
  }

  @override
  List<Object?> get props => [
    reference,
    departure,
    seat,
    status,
    reservationId,
    qrCode,
    ticketIds,
    legs,
    arrivesAt,
  ];
}
