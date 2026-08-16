import 'package:flutter/material.dart';

/// Palette de marque KORA — couleurs **fixes**, identiques quel que soit
/// le thème (contrairement à [KoraColors], contextuelles clair/sombre).
/// Nomenclature alignée sur `packages/flutter-design-system` de
/// `kora-passager` (voir document.md § Design System) pour que les deux
/// apps du monorepo restent uniformes.
abstract final class AppColors {
  static const Color kora = Color(0xFF001D4E);
  static const Color gold = Color(0xFFF6B000);
  static const Color digital = Color(0xFF0057B8);
  static const Color white = Color(0xFFFFFFFF);
  static const Color black = Color(0xFF000000);

  // États sémantiques
  static const Color success = Color(0xFF2D7A51);
  static const Color warning = Color(0xFFC77700);
  static const Color danger = Color(0xFF9C2430);
  static const Color info = digital;

  /// Texte lisible sur un fond `gold`/`warning` (teintes claires) : le
  /// blanc n'offre pas assez de contraste.
  static const Color onGold = Color(0xFF1A1400);

  // Fonds "mist" fixes (mode clair) — voir KoraColors pour l'équivalent
  // contextuel utilisé dans les écrans.
  static const Color mist = Color(0xFFF4F6F8);
}
