part of 'language_bloc.dart';

sealed class LanguageEvent extends Equatable {
  const LanguageEvent();

  @override
  List<Object?> get props => [];
}

/// Émis au démarrage de l'app : relit la langue sauvegardée.
class LanguageStarted extends LanguageEvent {
  const LanguageStarted();
}

/// Émis quand l'utilisateur choisit une langue dans les préférences.
class LanguageChanged extends LanguageEvent {
  const LanguageChanged(this.language);

  final AppLanguage language;

  @override
  List<Object?> get props => [language];
}
