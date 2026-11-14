import 'package:equatable/equatable.dart';

/// L'utilisateur connecté, tel que le renvoie l'API.
/// Par où un compte est entré chez KORA.
enum RegistrationOrigin {
  /// Inscription par numéro : c'est lui qui identifie le compte.
  phone,

  /// Inscription par adresse email.
  email,

  /// Connexion Google ou Apple : l'adresse vient du fournisseur, et
  /// c'est elle qui identifie le compte.
  social,

  /// Compte ouvert au guichet à partir d'un numéro.
  assisted,

  /// Le serveur ne sait pas — un compte d'avant ce champ.
  unknown;

  static RegistrationOrigin of(String? raw) => switch (raw) {
    'phone' => RegistrationOrigin.phone,
    'email' => RegistrationOrigin.email,
    'social' => RegistrationOrigin.social,
    'assisted' => RegistrationOrigin.assisted,
    _ => RegistrationOrigin.unknown,
  };

  /// Vrai quand c'est le **numéro** qui identifie le compte.
  bool get identifiesByPhone =>
      this == RegistrationOrigin.phone || this == RegistrationOrigin.assisted;

  /// Vrai quand c'est l'**adresse** qui identifie le compte.
  bool get identifiesByEmail =>
      this == RegistrationOrigin.email || this == RegistrationOrigin.social;
}

class AuthUser extends Equatable {
  const AuthUser({
    required this.uuid,
    required this.name,
    required this.email,
    this.phone,
    this.countryCode,
    this.registrationOrigin = RegistrationOrigin.unknown,
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

  /// Comment le compte a été ouvert.
  ///
  /// L'écran « Mes informations » verrouille l'identifiant qui a servi à
  /// s'inscrire et laisse compléter l'autre. Il le déduisait faute de
  /// mieux ; le serveur le dit désormais.
  final RegistrationOrigin registrationOrigin;
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
