import 'package:flutter/material.dart';

/// Palette KORA — valeurs extraites des variables CSS du prototype web
/// "KORA App" (`KORA_App_Final.zip` / css/style.css), afin que KORA OPS
/// Mobile partage exactement la même identité visuelle que l'app passagers,
/// bien que ce soit une application distincte.
///
/// Correspondance avec les variables CSS d'origine :
///   --kora     -> primary
///   --gold     -> accent
///   --white    -> white
///   --digital  -> info / secondary
///   --mist     -> background
///   --graphite -> textPrimary
///   --muted    -> textSecondary (rgba(17,24,39,.6))
///   --line     -> border (rgba(0,29,78,.11))
abstract final class AppColors {
  // Couleurs de marque
  static const Color primary = Color(0xFF001D4E); // --kora
  static const Color accent = Color(0xFFF6B000); // --gold
  static const Color secondary = Color(0xFF0057B8); // --digital
  static const Color white = Color(0xFFFFFFFF); // --white

  // Surfaces
  static const Color background = Color(0xFFF4F6F8); // --mist
  static const Color surface = Color(0xFFFFFFFF);
  static const Color border = Color(0x1C001D4E); // --line rgba(0,29,78,.11)

  // Texte
  static const Color textPrimary = Color(0xFF111827); // --graphite
  static const Color textSecondary = Color(0x99111827); // --muted rgba(17,24,39,.6)
  static const Color textOnPrimary = white;

  // États sémantiques (dérivés de la charte, absents du prototype passagers
  // mais nécessaires aux écrans opérationnels : validation, erreurs terrain)
  static const Color success = Color(0xFF2D7A51);
  static const Color warning = Color(0xFFF6B000); // aligné sur --gold
  static const Color error = Color(0xFF9C2430);
  static const Color info = secondary;

  // Ombres
  static const Color shadow = Color(0x24001D4E); // --shadow rgba(0,29,78,.14)
}
