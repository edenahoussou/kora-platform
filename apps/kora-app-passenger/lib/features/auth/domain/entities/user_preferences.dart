import 'package:equatable/equatable.dart';

/// Réglages du compte, partagés entre les appareils (`auth/preferences`).
///
/// La langue et le thème vivent aussi en local (`SharedPreferences`, via
/// `LanguageBloc` et `ThemeBloc`) : l'app doit pouvoir s'afficher dans la
/// bonne langue avant même de savoir qui est connecté. Le serveur porte
/// la version de référence, qui suit l'utilisateur d'un téléphone à
/// l'autre ; le local en est le reflet.
class UserPreferences extends Equatable {
  const UserPreferences({
    this.pushNotifications = true,
    this.whatsappNotifications = true,
    this.smsNotifications = true,
    this.emailNotifications = false,
    this.locale = 'fr',
    this.theme = 'system',
  });

  final bool pushNotifications;
  final bool whatsappNotifications;
  final bool smsNotifications;
  final bool emailNotifications;

  /// `fr` ou `en`, les deux seules valeurs acceptées par l'API.
  final String locale;

  /// `light`, `dark` ou `system`.
  final String theme;

  UserPreferences copyWith({
    bool? pushNotifications,
    bool? whatsappNotifications,
    bool? smsNotifications,
    bool? emailNotifications,
    String? locale,
    String? theme,
  }) {
    return UserPreferences(
      pushNotifications: pushNotifications ?? this.pushNotifications,
      whatsappNotifications:
          whatsappNotifications ?? this.whatsappNotifications,
      smsNotifications: smsNotifications ?? this.smsNotifications,
      emailNotifications: emailNotifications ?? this.emailNotifications,
      locale: locale ?? this.locale,
      theme: theme ?? this.theme,
    );
  }

  @override
  List<Object?> get props => [
    pushNotifications,
    whatsappNotifications,
    smsNotifications,
    emailNotifications,
    locale,
    theme,
  ];
}
