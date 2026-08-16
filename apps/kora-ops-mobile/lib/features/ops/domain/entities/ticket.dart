import 'package:equatable/equatable.dart';

/// Billet associé à un passager — les données de scan (§ MEZ-10) et de
/// détail passager s'appuient dessus.
class Ticket extends Equatable {
  const Ticket({
    required this.id,
    required this.numero,
    required this.passengerId,
    required this.departId,
    required this.siege,
    required this.segmentOrigine,
    required this.segmentDestination,
    required this.qrData,
  });

  final String id;

  /// Ex : "KORA-TICKET-000123"
  final String numero;

  final String passengerId;
  final String departId;
  final String siege;
  final String segmentOrigine;
  final String segmentDestination;

  /// Charge utile encodée dans le QR imprimé sur le billet (voir
  /// `features/printing/domain/entities/ticket_payload.dart`, MEZ-10).
  final String qrData;

  @override
  List<Object?> get props => <Object?>[
        id,
        numero,
        passengerId,
        departId,
        siege,
        segmentOrigine,
        segmentDestination,
        qrData,
      ];
}
