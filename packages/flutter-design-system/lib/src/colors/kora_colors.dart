import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Couleurs "contextuelles" : leur valeur change selon le thème actif
/// (clair ou sombre). C'est différent de [AppColors], qui contient des
/// couleurs fixes (le jaune KORA reste jaune, peu importe le thème).
///
/// Exemple concret : le fond d'une card est BLANC en mode clair, mais
/// BLEU FONCÉ en mode sombre. On ne peut donc pas mettre ça dans
/// `AppColors`. On utilise le mécanisme officiel de Flutter pour ça :
/// `ThemeExtension`.
///
/// Comment un débutant utilise ça dans un widget :
/// ```dart
/// final koraColors = Theme.of(context).extension<KoraColors>()!;
/// return Container(color: koraColors.surface);
/// ```
/// Flutter choisit automatiquement la bonne extension (light ou dark)
/// selon le thème actif — on n'a JAMAIS besoin d'écrire
/// `if (isDarkMode) ... else ...` dans les widgets.
@immutable
class KoraColors extends ThemeExtension<KoraColors> {
  const KoraColors({
    required this.background,
    required this.surface,
    required this.textPrimary,
    required this.textMuted,
    required this.line,
    required this.cardShadow,
  });

  /// Fond général de l'écran.
  final Color background;

  /// Fond des cards / éléments surélevés.
  final Color surface;

  /// Couleur de texte principale (titres, texte fort).
  final Color textPrimary;

  /// Couleur de texte secondaire (sous-titres, texte atténué).
  final Color textMuted;

  /// Couleur des bordures / séparateurs.
  final Color line;

  /// Ombre portée sous les cards (vide en dark mode, cf. AppShadows).
  final List<BoxShadow> cardShadow;

  static const KoraColors light = KoraColors(
    background: AppColors.mist,
    surface: AppColors.white,
    textPrimary: AppColors.graphite,
    textMuted: AppColors.mutedLight,
    line: AppColors.lineLight,
    cardShadow: [
      BoxShadow(
        color: Color(0x0E001D4E), // kora à ~5.5% d'opacité
        offset: Offset(0, 8),
        blurRadius: 25,
      ),
    ],
  );

  static const KoraColors dark = KoraColors(
    background: AppColors.backgroundDark,
    surface: AppColors.surfaceDark,
    textPrimary: AppColors.textDark,
    textMuted: AppColors.mutedDark,
    line: AppColors.lineDark,
    cardShadow: [], // pas d'ombre visible sur fond sombre
  );

  @override
  KoraColors copyWith({
    Color? background,
    Color? surface,
    Color? textPrimary,
    Color? textMuted,
    Color? line,
    List<BoxShadow>? cardShadow,
  }) {
    return KoraColors(
      background: background ?? this.background,
      surface: surface ?? this.surface,
      textPrimary: textPrimary ?? this.textPrimary,
      textMuted: textMuted ?? this.textMuted,
      line: line ?? this.line,
      cardShadow: cardShadow ?? this.cardShadow,
    );
  }

  @override
  KoraColors lerp(ThemeExtension<KoraColors>? other, double t) {
    if (other is! KoraColors) return this;
    return KoraColors(
      background: Color.lerp(background, other.background, t)!,
      surface: Color.lerp(surface, other.surface, t)!,
      textPrimary: Color.lerp(textPrimary, other.textPrimary, t)!,
      textMuted: Color.lerp(textMuted, other.textMuted, t)!,
      line: Color.lerp(line, other.line, t)!,
      cardShadow: t < 0.5 ? cardShadow : other.cardShadow,
    );
  }
}

/// Petite extension de confort pour éviter d'écrire
/// `Theme.of(context).extension<KoraColors>()!` partout.
///
/// Utilisation : `context.koraColors.surface`
extension KoraColorsContext on BuildContext {
  KoraColors get koraColors => Theme.of(this).extension<KoraColors>()!;
}
