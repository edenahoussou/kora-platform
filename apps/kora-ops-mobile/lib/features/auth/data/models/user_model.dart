import '../../domain/entities/user_entity.dart';

/// Model = Entity + (de)sérialisation JSON. Le Domain ne doit jamais
/// dépendre de ce fichier.
class UserModel extends UserEntity {
  const UserModel({
    required super.id,
    required super.fullName,
    required super.role,
    required super.email,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) => UserModel(
        id: json['id'] as String,
        fullName: json['full_name'] as String,
        role: json['role'] as String,
        email: json['email'] as String,
      );

  Map<String, dynamic> toJson() => <String, dynamic>{
        'id': id,
        'full_name': fullName,
        'role': role,
        'email': email,
      };

  factory UserModel.fromEntity(UserEntity entity) => UserModel(
        id: entity.id,
        fullName: entity.fullName,
        role: entity.role,
        email: entity.email,
      );
}
