import 'package:flutter/material.dart';

/// Échelle typographique KORA. Police extraite du prototype web :
///   titres : "Aptos Display", Aptos, "Segoe UI", sans-serif
///   texte  : Aptos, "Segoe UI", Arial, sans-serif
///
/// API alignée sur `kora-passager` (`AppTextStyles.xxx(color)`) : chaque
/// style est une **fonction** qui prend la couleur en paramètre plutôt
/// qu'un `TextStyle` figé — l'appelant passe `context.koraColors.textPrimary`
/// ou `context.koraColors.textMuted` selon le contexte clair/sombre.
abstract final class AppTextStyles {
  static const String _displayFontFamily = 'Aptos Display';
  static const String _bodyFontFamily = 'Aptos';
  static const List<String> _fallback = <String>['Segoe UI', 'Roboto', 'Arial'];

  static TextStyle eyebrow(Color color) => TextStyle(
        fontFamily: _bodyFontFamily,
        fontFamilyFallback: _fallback,
        fontSize: 11,
        fontWeight: FontWeight.w800,
        letterSpacing: 0.6,
        color: color,
      );

  static TextStyle heading1(Color color) => TextStyle(
        fontFamily: _displayFontFamily,
        fontFamilyFallback: _fallback,
        fontSize: 23,
        fontWeight: FontWeight.w800,
        letterSpacing: -0.575,
        color: color,
      );

  static TextStyle heading2(Color color) => TextStyle(
        fontFamily: _displayFontFamily,
        fontFamilyFallback: _fallback,
        fontSize: 19,
        fontWeight: FontWeight.w700,
        color: color,
      );

  static TextStyle titleMedium(Color color) => TextStyle(
        fontFamily: _bodyFontFamily,
        fontFamilyFallback: _fallback,
        fontSize: 16,
        fontWeight: FontWeight.w700,
        color: color,
      );

  static TextStyle lead(Color color) => TextStyle(
        fontFamily: _bodyFontFamily,
        fontFamilyFallback: _fallback,
        fontSize: 14,
        height: 1.4,
        color: color,
      );

  static TextStyle body(Color color) => TextStyle(
        fontFamily: _bodyFontFamily,
        fontFamilyFallback: _fallback,
        fontSize: 13,
        height: 1.4,
        color: color,
      );

  static TextStyle caption(Color color) => TextStyle(
        fontFamily: _bodyFontFamily,
        fontFamilyFallback: _fallback,
        fontSize: 11,
        color: color,
      );

  static TextStyle button(Color color) => TextStyle(
        fontFamily: _bodyFontFamily,
        fontFamilyFallback: _fallback,
        fontSize: 15,
        fontWeight: FontWeight.w700,
        letterSpacing: 0.2,
        color: color,
      );
}
