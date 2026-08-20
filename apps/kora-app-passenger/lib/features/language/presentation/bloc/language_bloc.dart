import 'package:equatable/equatable.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/app_language.dart';
import '../../domain/repositories/language_repository.dart';

part 'language_event.dart';
part 'language_state.dart';

/// Couche PRESENTATION : le seul endroit de l'app qui décide quelle langue
/// est active. Même découpage que `ThemeBloc`, avec lequel il partage le
/// rôle : une préférence d'affichage, relue au démarrage et sauvegardée
/// dès qu'elle change.
///
/// Branchement (voir `lib/app.dart`) :
/// ```dart
/// BlocProvider(
///   create: (_) => getIt<LanguageBloc>()..add(const LanguageStarted()),
///   child: ...
/// )
/// ```
class LanguageBloc extends Bloc<LanguageEvent, LanguageState> {
  LanguageBloc(this._repository) : super(const LanguageState()) {
    on<LanguageStarted>(_onStarted);
    on<LanguageChanged>(_onChanged);
  }

  final LanguageRepository _repository;

  Future<void> _onStarted(
    LanguageStarted event,
    Emitter<LanguageState> emit,
  ) async {
    emit(state.copyWith(language: await _repository.getLanguage()));
  }

  Future<void> _onChanged(
    LanguageChanged event,
    Emitter<LanguageState> emit,
  ) async {
    // Émis avant la sauvegarde : l'écran change de langue tout de suite,
    // l'écriture disque n'a pas à le faire attendre.
    emit(state.copyWith(language: event.language));
    await _repository.setLanguage(event.language);
  }
}
