import 'package:equatable/equatable.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/app_theme_mode.dart';
import '../../domain/repositories/theme_repository.dart';

part 'theme_event.dart';
part 'theme_state.dart';

/// Couche PRESENTATION : le Bloc fait le lien entre les Events (entrées) et
/// les States (sorties). C'est le SEUL endroit de l'app qui décide "quel
/// est le thème actif en ce moment".
///
/// Il ne parle jamais directement à SharedPreferences : il passe toujours
/// par [ThemeRepository] (couche domain), qui lui-même délègue à la couche
/// data. C'est ça, la Clean Architecture : chaque couche ne connaît que la
/// couche juste en dessous d'elle, jamais les détails techniques du fond.
///
/// Comment le brancher dans l'app (voir `lib/app.dart`) :
/// ```dart
/// BlocProvider(
///   create: (_) => getIt<ThemeBloc>()..add(const ThemeStarted()),
///   child: ...
/// )
/// ```
class ThemeBloc extends Bloc<ThemeEvent, ThemeState> {
  ThemeBloc(this._repository) : super(const ThemeState()) {
    on<ThemeStarted>(_onStarted);
    on<ThemeModeChanged>(_onModeChanged);
    on<ThemeToggled>(_onToggled);
  }

  final ThemeRepository _repository;

  Future<void> _onStarted(ThemeStarted event, Emitter<ThemeState> emit) async {
    final savedMode = await _repository.getThemeMode();
    emit(state.copyWith(mode: savedMode));
  }

  Future<void> _onModeChanged(
    ThemeModeChanged event,
    Emitter<ThemeState> emit,
  ) async {
    emit(state.copyWith(mode: event.mode));
    await _repository.setThemeMode(event.mode);
  }

  Future<void> _onToggled(ThemeToggled event, Emitter<ThemeState> emit) async {
    // Si on est actuellement en "system", on bascule en fonction de ce que
    // le système affiche réellement, pour que le toggle ait toujours un
    // effet visible et prévisible pour l'utilisateur.
    final brightness =
        WidgetsBinding.instance.platformDispatcher.platformBrightness;
    final isCurrentlyDark =
        state.mode == AppThemeMode.dark ||
        (state.mode == AppThemeMode.system && brightness == Brightness.dark);

    final newMode = isCurrentlyDark ? AppThemeMode.light : AppThemeMode.dark;
    emit(state.copyWith(mode: newMode));
    await _repository.setThemeMode(newMode);
  }
}
