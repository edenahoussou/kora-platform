import 'package:flutter/material.dart';
import 'app_colors.dart';
import 'app_radius.dart';
import 'app_text_styles.dart';
import 'kora_colors.dart';

/// Thème global de l'application. [light] et [dark] partagent la même
/// structure et n'diffèrent que par [KoraColors] (surface/fond/texte),
/// enregistrée comme `ThemeExtension` — voir document.md § Dark mode.
abstract final class AppTheme {
  static ThemeData get light =>
      _build(brightness: Brightness.light, koraColors: KoraColors.light);

  static ThemeData get dark =>
      _build(brightness: Brightness.dark, koraColors: KoraColors.dark);

  static ThemeData _build(
      {required Brightness brightness, required KoraColors koraColors}) {
    final ColorScheme colorScheme = ColorScheme.fromSeed(
      seedColor: AppColors.kora,
      primary: AppColors.kora,
      secondary: AppColors.digital,
      tertiary: AppColors.gold,
      surface: koraColors.surface,
      error: AppColors.danger,
      brightness: brightness,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: koraColors.background,
      fontFamily: 'Aptos',
      extensions: <ThemeExtension<dynamic>>[koraColors],
      textTheme: TextTheme(
        displayMedium: AppTextStyles.heading1(koraColors.textPrimary),
        headlineMedium: AppTextStyles.heading2(koraColors.textPrimary),
        titleMedium: AppTextStyles.titleMedium(koraColors.textPrimary),
        bodyLarge: AppTextStyles.lead(koraColors.textPrimary),
        bodyMedium: AppTextStyles.body(koraColors.textPrimary),
        bodySmall: AppTextStyles.caption(koraColors.textMuted),
        labelLarge: AppTextStyles.button(AppColors.onGold),
      ),
      iconTheme: IconThemeData(color: koraColors.textPrimary),
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.kora,
        foregroundColor: AppColors.white,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: TextStyle(
          fontFamily: 'Aptos Display',
          fontSize: 18,
          fontWeight: FontWeight.w600,
          color: AppColors.white,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.gold,
          foregroundColor: AppColors.onGold,
          textStyle: AppTextStyles.button(AppColors.onGold),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AppRadius.md)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor:
              brightness == Brightness.dark ? AppColors.white : AppColors.kora,
          side: BorderSide(
              color: brightness == Brightness.dark
                  ? AppColors.white
                  : AppColors.kora),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AppRadius.md)),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: AppColors.digital),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: koraColors.surface,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        labelStyle: TextStyle(color: koraColors.textMuted),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: BorderSide(color: koraColors.line),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: BorderSide(color: koraColors.line),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: const BorderSide(color: AppColors.gold, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: const BorderSide(color: AppColors.danger),
        ),
      ),
      cardTheme: CardThemeData(
        color: koraColors.surface,
        elevation: 2,
        shadowColor: koraColors.shadow,
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.lg)),
      ),
      dividerTheme: DividerThemeData(color: koraColors.line, thickness: 1),
    );
  }
}
