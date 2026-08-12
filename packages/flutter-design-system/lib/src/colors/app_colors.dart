import 'package:flutter/material.dart';

/// Palette de couleurs officielle KORA EXPRESS.
///
/// Toutes ces valeurs sont extraites directement du prototype de référence
/// (`docs/maquettes/kora-app-passenger/css/style.css`, bloc `:root`). Ne jamais écrire une
/// couleur "en dur" (ex: `Color(0xFF001D4E)`) ailleurs dans le code : on
/// passe toujours par une constante définie ici.
///
/// Astuce pour un débutant : si demain le service marketing change le bleu
/// KORA, il suffit de modifier UNE seule ligne ici, et toutes les apps qui
/// dépendent de `flutter_design_system` se mettent à jour automatiquement.
class AppColors {
  const AppColors._();

  // --- Couleurs de marque (identiques en light & dark) ---

  /// Bleu marine KORA — couleur principale de la marque.
  /// CSS : --kora: #001D4E
  static const Color kora = Color(0xFF001D4E);

  /// Jaune/or KORA — couleur d'accent (boutons principaux, highlights).
  /// CSS : --gold: #F6B000
  static const Color gold = Color(0xFFF6B000);

  /// Bleu "digital" — utilisé pour les liens et accents secondaires.
  /// CSS : --digital: #0057B8
  static const Color digital = Color(0xFF0057B8);

  static const Color white = Color(0xFFFFFFFF);

  // --- Couleurs de fond & texte (mode clair) ---

  /// Fond gris très clair utilisé derrière les cards / sections.
  /// CSS : --mist: #F4F6F8
  static const Color mist = Color(0xFFF4F6F8);

  /// Couleur de texte principal en mode clair.
  /// CSS : --graphite: #111827
  static const Color graphite = Color(0xFF111827);

  /// Texte secondaire / atténué en mode clair (graphite à 60% d'opacité).
  static const Color mutedLight = Color(0x99111827);

  /// Couleur des séparateurs / bordures en mode clair (kora à 11%).
  static const Color lineLight = Color(0x1C001D4E);

  // --- Couleurs de fond & texte (mode sombre) ---
  //
  // Le prototype HTML applique `.dark-mode` en gardant le bleu KORA comme
  // fond d'écran principal (voir css/style.css ~L1714). On garde cette
  // logique : en dark mode, le "fond de page" devient le bleu marine KORA
  // et les cards passent sur une variante plus soutenue de ce bleu.

  /// Fond d'écran principal en mode sombre (repris du `.dark-mode` du CSS).
  static const Color backgroundDark = kora;

  /// Fond des cards en mode sombre (légèrement plus clair que le fond,
  /// pour créer de la profondeur — pratique courante en dark mode).
  static const Color surfaceDark = Color(0xFF00153E);

  /// Texte principal en mode sombre.
  static const Color textDark = white;

  /// Texte secondaire en mode sombre.
  static const Color mutedDark = Color(0x8CFFFFFF);

  /// Séparateurs / bordures en mode sombre.
  static const Color lineDark = Color(0x24FFFFFF);

  // --- États sémantiques (succès / erreur), utiles pour Wallet/colis ---

  /// Vert utilisé pour les statuts positifs (ex: livraison réussie).
  static const Color success = Color(0xFF2D7A51);

  /// Rouge utilisé pour les statuts négatifs (ex: incident, erreur).
  static const Color danger = Color(0xFF9C2430);
}
