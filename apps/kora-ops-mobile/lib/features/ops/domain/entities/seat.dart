import 'package:equatable/equatable.dart';

enum SeatStatus { occupe, libere, reserve }

/// Un siège du véhicule — utilisé par `SeatMap` (Descente anticipée) pour
/// visualiser les places libérées sur les segments suivants.
class Seat extends Equatable {
  const Seat({
    required this.numero,
    required this.statut,
    this.passengerName,
    this.libereAPartirDe,
  });

  /// Ex : "12A"
  final String numero;

  final SeatStatus statut;

  final String? passengerName;

  /// Segment à partir duquel le siège se libère (ex : "Bohicon") — null si
  /// le siège reste occupé jusqu'au terminus ou est déjà libre.
  final String? libereAPartirDe;

  @override
  List<Object?> get props =>
      <Object?>[numero, statut, passengerName, libereAPartirDe];
}
