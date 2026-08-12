part of 'theme_bloc.dart';

/// Couche PRESENTATION : le State représente "ce que l'écran doit
/// afficher" à un instant T. Ici, un seul State suffit (pas besoin de
/// Loading/Error) car lire une préférence locale est quasi instantané et ne
/// peut pas vraiment échouer — on garde donc les choses simples plutôt que
/// d'ajouter de la complexité inutile.
class ThemeState extends Equatable {
  const ThemeState({this.mode = AppThemeMode.system});

  final AppThemeMode mode;

  /// Convertit notre [AppThemeMode] (métier) vers le [ThemeMode] attendu
  /// par `MaterialApp`. C'est la SEULE classe de la couche presentation qui
  /// fait ce pont entre "notre métier" et "l'API Flutter".
  ThemeMode get materialThemeMode {
    switch (mode) {
      case AppThemeMode.light:
        return ThemeMode.light;
      case AppThemeMode.dark:
        return ThemeMode.dark;
      case AppThemeMode.system:
        return ThemeMode.system;
    }
  }

  ThemeState copyWith({AppThemeMode? mode}) {
    return ThemeState(mode: mode ?? this.mode);
  }

  @override
  List<Object?> get props => [mode];
}
