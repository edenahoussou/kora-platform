import 'package:flutter/material.dart';
import 'app_colors.dart';

/// Typographie KORA — police extraite du prototype web :
///   titres : "Aptos Display", Aptos, "Segoe UI", sans-serif
///   texte  : Aptos, "Segoe UI", Arial, sans-serif
///
/// "Aptos" n'étant pas une police système sur Android/iOS, on déclare
/// `fontFamilyFallback` vers les équivalents système les plus proches.
/// Si les fichiers de police Aptos sont fournis par la marque, les ajouter
/// dans `pubspec.yaml` > `flutter > fonts` et remplacer `fontFamily` ici.
abstract final class AppTypography {
  static const String _displayFontFamily = 'Aptos Display';
  static const String _bodyFontFamily = 'Aptos';
  static const List<String> _fallback = <String>['Segoe UI', 'Roboto', 'Arial'];

  static const TextStyle displayLarge = TextStyle(
    fontFamily: _displayFontFamily,
    fontFamilyFallback: _fallback,
    fontSize: 32,
    fontWeight: FontWeight.w700,
    color: AppColors.textPrimary,
    height: 1.2,
  );

  static const TextStyle displayMedium = TextStyle(
    fontFamily: _displayFontFamily,
    fontFamilyFallback: _fallback,
    fontSize: 26,
    fontWeight: FontWeight.w700,
    color: AppColors.textPrimary,
    height: 1.25,
  );

  static const TextStyle headline = TextStyle(
    fontFamily: _displayFontFamily,
    fontFamilyFallback: _fallback,
    fontSize: 20,
    fontWeight: FontWeight.w600,
    color: AppColors.textPrimary,
    height: 1.3,
  );

  static const TextStyle titleMedium = TextStyle(
    fontFamily: _bodyFontFamily,
    fontFamilyFallback: _fallback,
    fontSize: 17,
    fontWeight: FontWeight.w600,
    color: AppColors.textPrimary,
  );

  static const TextStyle bodyLarge = TextStyle(
    fontFamily: _bodyFontFamily,
    fontFamilyFallback: _fallback,
    fontSize: 16,
    fontWeight: FontWeight.w400,
    color: AppColors.textPrimary,
    height: 1.4,
  );

  static const TextStyle bodyMedium = TextStyle(
    fontFamily: _bodyFontFamily,
    fontFamilyFallback: _fallback,
    fontSize: 14,
    fontWeight: FontWeight.w400,
    color: AppColors.textPrimary,
    height: 1.4,
  );

  static const TextStyle caption = TextStyle(
    fontFamily: _bodyFontFamily,
    fontFamilyFallback: _fallback,
    fontSize: 12,
    fontWeight: FontWeight.w400,
    color: AppColors.textSecondary,
  );

  static const TextStyle button = TextStyle(
    fontFamily: _bodyFontFamily,
    fontFamilyFallback: _fallback,
    fontSize: 15,
    fontWeight: FontWeight.w600,
    color: AppColors.white,
    letterSpacing: 0.2,
  );
}
