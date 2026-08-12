import 'package:flutter/material.dart';

import '../colors/app_colors.dart';

/// Styles de texte de l'application.
///
/// Le prototype utilise la police "Aptos" (police système Microsoft, non
/// disponible sur pub.dev / Google Fonts). On utilise donc la police système
/// par défaut de Flutter (qui est déjà très proche visuellement : Roboto sur
/// Android, SF Pro sur iOS). Si un jour le client fournit les fichiers de la
/// police Aptos, il suffira de les ajouter dans les assets de ce package et
/// de déclarer une seule ligne ici (`fontFamily: 'Aptos'`) — rien d'autre à
/// changer côté apps consommatrices.
///
/// Chaque style ci-dessous prend une [Color] en paramètre : c'est
/// [AppTheme] qui décide de la couleur (kora en light, blanc en dark), les
/// widgets ne choisissent jamais la couleur de texte eux-mêmes.
class AppTextStyles {
  const AppTextStyles._();

  /// Grand titre d'écran (H1). CSS de référence : .app-header h2
  /// (23px, 800, letter-spacing -.025em = 23 × -0.025 = -0.575px).
  static TextStyle heading1(Color color) => TextStyle(
    fontSize: 23,
    fontWeight: FontWeight.w800,
    letterSpacing: -0.575,
    height: 1.08,
    color: color,
  );

  /// Titre de section (H2). CSS : .section-title h3
  /// (17px, letter-spacing -.02em = 17 × -0.02 = -0.34px).
  static TextStyle heading2(Color color) => TextStyle(
    fontSize: 17,
    fontWeight: FontWeight.w800,
    letterSpacing: -0.34,
    color: color,
  );

  /// Titre de card / élément fort. CSS : .card-title strong (15px)
  static TextStyle titleMedium(Color color) =>
      TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: color);

  /// Texte courant / paragraphe DANS une card. CSS : .card p
  /// (12px, line-height 1.42). À ne pas confondre avec [lead], plus grand
  /// (13px), qui sert de sous-titre d'écran sous un [heading1].
  static TextStyle body(Color color) => TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w400,
    height: 1.42,
    color: color,
  );

  /// Texte d'intro / sous-titre d'écran, affiché sous un [heading1]. CSS :
  /// .lead (13px, line-height 1.45). Valeur précédemment (et par erreur)
  /// assignée à [body] — séparée ici pour que chaque style corresponde
  /// exactement à sa classe CSS d'origine.
  static TextStyle lead(Color color) => TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w400,
    height: 1.45,
    color: color,
  );

  /// Petit texte secondaire (sous-titres, légendes). CSS : .lead, small
  static TextStyle caption(Color color) =>
      TextStyle(fontSize: 11, fontWeight: FontWeight.w500, color: color);

  /// Label en majuscules, très petit, espacé — CSS : .eyebrow (10px, 800)
  static TextStyle eyebrow(Color color) => TextStyle(
    fontSize: 10,
    fontWeight: FontWeight.w800,
    letterSpacing: 1.2,
    color: color,
  );

  /// Texte des boutons. CSS : .primary/.secondary (font-weight 900)
  static TextStyle button(Color color) =>
      TextStyle(fontSize: 14, fontWeight: FontWeight.w900, color: color);

  /// Texte de badge/tag. CSS : .tag, .status
  /// (9px, 900, uppercase, letter-spacing .06em = 9 × 0.06 = 0.54px).
  static TextStyle tag(Color color) => TextStyle(
    fontSize: 9,
    fontWeight: FontWeight.w900,
    letterSpacing: 0.54,
    color: color,
  );

  // --- Raccourcis pratiques pour le mode clair (les plus utilisés) ---
  static TextStyle get heading1Light => heading1(AppColors.kora);
  static TextStyle get heading2Light => heading2(AppColors.kora);
  static TextStyle get bodyLight => body(AppColors.graphite);
  static TextStyle get leadLight => lead(AppColors.mutedLight);
  static TextStyle get captionLight => caption(AppColors.mutedLight);
}
