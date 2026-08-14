import 'package:equatable/equatable.dart';

/// Données nécessaires à l'impression d'un ticket 58mm.
///
/// [qrData] est la charge utile brute encodée dans le QR (ex : référence de
/// réservation) — c'est le SDK natif P501 qui génère le code-barres/QR
/// visuel à partir de cette chaîne ; le format ASCII produit par
/// [TicketFormatter] n'affiche qu'un repère visuel "[ QR CODE ]" pour la
/// prévisualisation et les tests, pas le QR lui-même.
class TicketPayload extends Equatable {
  const TicketPayload({
    required this.tripRoute,
    required this.tripDate,
    required this.tripTime,
    required this.passengerName,
    required this.seatNumber,
    required this.qrData,
    this.fiscalFooter = 'Merci de votre confiance - KORA EXPRESS',
  });

  /// Ex : "Cotonou - Porto-Novo"
  final String tripRoute;

  /// Ex : "14/08/2026"
  final String tripDate;

  /// Ex : "07:30"
  final String tripTime;

  final String passengerName;

  /// Ex : "12A"
  final String seatNumber;

  /// Référence encodée dans le QR (ex : identifiant de billet).
  final String qrData;

  /// Mention fiscale/légale imprimée en pied de ticket.
  final String fiscalFooter;

  @override
  List<Object?> get props => <Object?>[
        tripRoute,
        tripDate,
        tripTime,
        passengerName,
        seatNumber,
        qrData,
        fiscalFooter,
      ];
}
