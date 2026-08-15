import 'package:flutter/material.dart';

/// Palette KORA — valeurs extraites des variables CSS du prototype web
/// "KORA App" (`KORA_App_Final.zip` / css/style.css), afin que KORA OPS
/// Mobile partage exactement la même identité visuelle que l'app passagers.
///
/// Correspondance avec les variables CSS d'origine :
///   --kora     -> primary
///   --gold     -> accent
///   --white    -> white
///   --digital  -> secondary
///   --mist     -> background (clair)
///   --graphite -> textPrimary (clair)
///   --muted    -> textSecondary (clair, rgba(17,24,39,.6))
///   --line     -> border (clair, rgba(0,29,78,.11))
///
/// Le mode sombre n'existe pas dans le prototype web d'origine : les tokens
/// `*Dark` ci-dessous sont dérivés de la même identité de marque (voir
/// document.md § Dark mode) — `primary` (déjà un bleu marine très sombre)
/// sert directement de fond en mode sombre, comme sur l'écran "PIN KORA" de
/// la maquette fournie.
abstract final class AppColors {
  // Couleurs de marque (identiques clair/sombre)
  static const Color primary = Color(0xFF001D4E); // --kora
  static const Color accent = Color(0xFFF6B000); // --gold
  static const Color secondary = Color(0xFF0057B8); // --digital
  static const Color white = Color(0xFFFFFFFF); // --white
  static const Color black = Color(0xFF000000);

  // --- Mode clair ---
  static const Color background = Color(0xFFF4F6F8); // --mist
  static const Color surface = Color(0xFFFFFFFF);
  static const Color border = Color(0x1C001D4E); // --line rgba(0,29,78,.11)
  static const Color textPrimary = Color(0xFF111827); // --graphite
  static const Color textSecondary =
      Color(0x99111827); // --muted rgba(17,24,39,.6)
  static const Color textOnPrimary = white;
  static const Color shadow = Color(0x24001D4E); // --shadow rgba(0,29,78,.14)

  // --- Mode sombre ---
  // `primary` réutilisé comme fond (voir écran "Votre PIN KORA" de la
  // maquette) ; `surfaceDark` légèrement plus clair pour distinguer les
  // cartes du fond.
  static const Color backgroundDark = primary;
  static const Color surfaceDark = Color(0xFF0B2A5C);
  static const Color borderDark = Color(0x33FFFFFF);
  static const Color textPrimaryDark = Color(0xFFF4F6F8);
  static const Color textSecondaryDark = Color(0x99F4F6F8);
  static const Color shadowDark = Color(0x40000000);

  // États sémantiques (communs clair/sombre — utilisés par AppButton et
  // DialogService). `warning` est volontairement distinct de `accent` :
  // l'or de marque sert de couleur d'action principale (voir AppButton),
  // `warning` doit rester identifiable séparément dans les alertes.
  static const Color success = Color(0xFF2D7A51);
  static const Color warning = Color(0xFFC77700);
  static const Color error = Color(0xFF9C2430);
  static const Color danger =
      error; // alias explicite pour AppButtonVariant.danger
  static const Color info = secondary;

  /// Texte lisible sur un fond `accent`/`warning` (tons or/ambre) : le blanc
  /// n'offre pas assez de contraste sur ces teintes claires.
  static const Color onAccent = Color(0xFF1A1400);
}
