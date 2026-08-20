part of 'language_bloc.dart';

class LanguageState extends Equatable {
  const LanguageState({this.language = AppLanguage.fallback});

  final AppLanguage language;

  /// La `Locale` à passer à `MaterialApp`.
  Locale get locale => Locale(language.code);

  LanguageState copyWith({AppLanguage? language}) {
    return LanguageState(language: language ?? this.language);
  }

  @override
  List<Object?> get props => [language];
}
