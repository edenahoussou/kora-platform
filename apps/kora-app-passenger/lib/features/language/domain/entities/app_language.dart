/// Les langues dans lesquelles l'app est disponible.
///
/// Le français est la langue par défaut : KORA EXPRESS opère au Bénin, où
/// c'est la langue officielle. L'anglais sert aux voyageurs de la sous-région
/// — le corridor Cotonou–Malanville dessert le Niger et le Nigeria.
enum AppLanguage {
  french('fr', 'Français'),
  english('en', 'English');

  const AppLanguage(this.code, this.label);

  /// Code ISO 639-1, aussi le nom du fichier de traduction
  /// (`assets/languages/<code>.json`) et la valeur stockée.
  final String code;

  /// Nom de la langue **dans cette langue** : quelqu'un qui cherche
  /// l'anglais reconnaît « English », pas « Anglais ».
  final String label;

  static const AppLanguage fallback = AppLanguage.french;

  /// Relit une valeur stockée ou un code de locale système. Retombe sur le
  /// français pour tout code inconnu : mieux vaut une langue lisible qu'un
  /// écran de clés brutes.
  static AppLanguage fromCode(String? code) {
    return AppLanguage.values.firstWhere(
      (language) => language.code == code?.toLowerCase(),
      orElse: () => fallback,
    );
  }
}
