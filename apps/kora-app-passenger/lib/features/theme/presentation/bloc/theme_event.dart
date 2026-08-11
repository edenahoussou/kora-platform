part of 'theme_bloc.dart';

/// Couche PRESENTATION : les Events représentent "ce qui se passe"
/// (une action de l'utilisateur, ou un événement système). Le Bloc ne fait
/// JAMAIS rien tout seul : il réagit toujours à un Event qu'on lui envoie.
///
/// Exemple dans un widget :
/// ```dart
/// context.read<ThemeBloc>().add(const ThemeToggled());
/// ```
sealed class ThemeEvent extends Equatable {
  const ThemeEvent();

  @override
  List<Object?> get props => [];
}

/// Envoyé une seule fois, au démarrage de l'application, pour charger le
/// thème précédemment sauvegardé par l'utilisateur.
class ThemeStarted extends ThemeEvent {
  const ThemeStarted();
}

/// Envoyé quand l'utilisateur choisit explicitement un mode
/// (ex: dans un écran de réglages avec 3 boutons Clair/Sombre/Système).
class ThemeModeChanged extends ThemeEvent {
  const ThemeModeChanged(this.mode);

  final AppThemeMode mode;

  @override
  List<Object?> get props => [mode];
}

/// Envoyé quand l'utilisateur bascule rapidement clair <-> sombre
/// (ex: un simple interrupteur dans le profil). Pratique quand on ne veut
/// pas exposer les 3 modes, juste un switch on/off.
class ThemeToggled extends ThemeEvent {
  const ThemeToggled();
}
