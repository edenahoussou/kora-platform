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
  ];
}
