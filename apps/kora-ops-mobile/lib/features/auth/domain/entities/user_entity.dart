import 'package:equatable/equatable.dart';

/// Entité métier — ne connaît rien du JSON ni de la couche Data.
class UserEntity extends Equatable {
  const UserEntity({required this.id, required this.fullName, required this.role});

  final String id;
  final String fullName;

  /// Rôle terrain : chauffeur, convoyeur, agent d'agence…
  final String role;

  @override
  List<Object?> get props => <Object?>[id, fullName, role];
}
