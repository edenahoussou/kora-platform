import 'package:equatable/equatable.dart';

import 'departure.dart';
import 'seat.dart';

/// Couche DOMAIN : le prochain voyage réservé par l'utilisateur — c'est ce
/// qu'affiche la carte « Prochain voyage » de l'accueil.
///
/// Ce n'est pas encore le billet complet (QR code, historique de paiement,
/// statut d'annulation...) : l'accueil n'a besoin que du départ concerné et
/// du siège attribué. Le billet complet viendra avec l'onglet Voyages.
class UpcomingTrip extends Equatable {
  const UpcomingTrip({required this.departure, required this.seat});

  final Departure departure;
  final Seat seat;

  /// Nombre de jours **entiers** avant le départ, calculé à partir de
  /// [now] (injecté plutôt que lu depuis `DateTime.now()` pour que ce
  /// calcul reste testable).
  ///
  /// On compare les dates au jour près (et pas les instants) : un départ
  /// demain matin à 07:30 doit afficher « Demain » même s'il est ce soir
  /// 23:00, soit moins de 24 h plus tard.
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
  List<Object?> get props => [departure, seat];
}
