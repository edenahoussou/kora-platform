import '../entities/app_language.dart';

/// Couche DOMAIN : le contrat, sans savoir où la langue est rangée.
abstract class LanguageRepository {
  /// Langue choisie par l'utilisateur, ou la langue par défaut s'il n'a
  /// jamais choisi.
  Future<AppLanguage> getLanguage();

  Future<void> setLanguage(AppLanguage language);
}
