import 'package:equatable/equatable.dart';

/// L'utilisateur OPS connecté, tel que le renvoie l'API
/// (`AuthUserResource` côté backend).
///
/// Remplace l'ancien `UserEntity.role` (texte libre, ex. « Convoyeur ») par
/// [permissions] : le même vocabulaire de permissions que KORA OPS Web
/// (`departures.view`, `boarding.scan`...), pour que l'app masque une
/// action plutôt que de la laisser échouer en 403 — voir
/// `SystemRole::fieldRoles()` côté backend pour la matrice des rôles
/// terrain (chauffeur, convoyeur, agent d'agence).
class AuthUser extends Equatable {
  const AuthUser({
    required this.uuid,
    required this.name,
    required this.email,
    this.phone,
    this.status = 'active',
    this.avatarUrl,
    this.permissions = const <String>[],
    this.roles = const <String>[],
    this.requiresTerminal = true,
  });

  final String uuid;
  final String name;
  final String email;
  final String? phone;

  /// `active`, `suspended`... — laissé ouvert, la liste n'est pas fermée
  /// côté API.
  final String status;

  final String? avatarUrl;

  /// Ce que le compte a le droit de faire (`boarding.scan`,
  /// `departures.view`...). Sert à masquer une action dans l'UI plutôt que
  /// de la laisser échouer en 403.
  final List<String> permissions;

  /// Codes des rôles système (`bus_driver`, `conductor`...) — voir
  /// `SystemRole` côté backend.
  final List<String> roles;

  /// Le compte doit-il passer par un terminal P501 (liaison + session OPS) ?
  /// Calculé par le serveur (`requires_terminal`, voir `OpsSessionGuard`) :
  /// `false` pour le chauffeur seul, `true` pour le convoyeur et tout autre
  /// rôle terrain — ou pour tous si `OPS_FORCE_TERMINAL_FOR_ALL=true`
  /// côté serveur. `true` par défaut (ancien serveur, cache plus ancien) :
  /// on garde alors le comportement historique (terminal obligatoire).
  final bool requiresTerminal;

  bool can(String permission) => permissions.contains(permission);

  bool hasRole(String role) => roles.contains(role);

  /// Chauffeur de bus (`bus_driver`).
  bool get isDriver => hasRole('bus_driver');

  /// Convoyeur (`conductor`).
  bool get isConductor => hasRole('conductor');

  /// Peut saisir le carnet de bord (kilométrage, carburant, péages et
  /// taxes frontières) — chauffeur et convoyeur.
  bool get canRecordTripLog => can('trip-logs.create');

  /// Vrai si le compte a au moins une des permissions données — pratique
  /// pour gater un onglet qui a plusieurs points d'entrée possibles.
  bool canAny(Iterable<String> anyOf) => anyOf.any(permissions.contains);

  /// Initiales affichées quand aucun avatar n'est disponible.
  String get initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty);
    if (parts.isEmpty) return '?';

    return parts
        .take(2)
        .map((part) => part.substring(0, 1).toUpperCase())
        .join();
  }

  AuthUser copyWith({
    String? uuid,
    String? name,
    String? email,
    String? phone,
    String? status,
    String? avatarUrl,
    List<String>? permissions,
    List<String>? roles,
    bool? requiresTerminal,
  }) {
    return AuthUser(
      uuid: uuid ?? this.uuid,
      name: name ?? this.name,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      status: status ?? this.status,
      avatarUrl: avatarUrl ?? this.avatarUrl,
      permissions: permissions ?? this.permissions,
      roles: roles ?? this.roles,
      requiresTerminal: requiresTerminal ?? this.requiresTerminal,
    );
  }

  @override
  List<Object?> get props => <Object?>[
        uuid,
        name,
        email,
        phone,
        status,
        avatarUrl,
        permissions,
        roles,
        requiresTerminal,
      ];
}
