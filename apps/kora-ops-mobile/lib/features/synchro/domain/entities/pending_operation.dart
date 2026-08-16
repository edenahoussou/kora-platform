import 'package:equatable/equatable.dart';

/// Une opération effectuée hors-ligne (embarquement, refus, descente
/// anticipée…) en attente d'être synchronisée avec le serveur.
class PendingOperation extends Equatable {
  const PendingOperation(
      {required this.id, required this.label, required this.createdAtLabel});

  final String id;

  /// Ex : "Embarquement — Siège 12A"
  final String label;

  /// Ex : "Il y a 2 min" — libellé déjà formaté (mock, pas de vraie horloge
  /// serveur ce sprint).
  final String createdAtLabel;

  @override
  List<Object?> get props => <Object?>[id, label, createdAtLabel];
}
