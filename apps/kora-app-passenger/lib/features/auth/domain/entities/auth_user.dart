import 'package:equatable/equatable.dart';

/// L'utilisateur connecté, tel que le renvoie l'API.
class AuthUser extends Equatable {
  const AuthUser({
    required this.uuid,
    required this.name,
    required this.email,
    this.phone,
    this.countryCode,
    this.birthDate,
    this.status = 'active',
    this.avatarUrl,
    this.permissions = const [],
  });

  final String uuid;
  final String name;
  final String email;
  final String? phone;
  final String? countryCode;
  final DateTime? birthDate;

  /// `active`, `suspended`... Laissé ouvert : la doc ne ferme pas la liste.
  final String status;

  /// Renseigné par la connexion sociale, qui récupère l'avatar du
  /// fournisseur.
  final String? avatarUrl;

  /// Ce que le compte a le droit de faire (`bookings.create`...). Sert à
  /// masquer une action plutôt que de la laisser échouer en 403.
  final List<String> permissions;

  bool can(String permission) => permissions.contains(permission);

  /// `AH` pour « Aïcha HOUNKPÈ » — l'avatar du profil quand aucune image
  /// n'est disponible.
  String get initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty);
    if (parts.isEmpty) return '?';

    return parts
        .take(2)
        .map((part) => part.substring(0, 1).toUpperCase())
        .join();
  }

  @override
  List<Object?> get props => [
    uuid,
    name,
    email,
    phone,
    countryCode,
    birthDate,
    status,
    avatarUrl,
    permissions,
  ];
}
