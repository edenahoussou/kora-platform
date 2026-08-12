import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';

/// Point d'entrée unique pour tout ce qui concerne le thème visuel.
///
/// [AppTheme.light] et [AppTheme.dark] retournent chacun un [ThemeData]
/// complet. C'est ce que consomme `MaterialApp` dans l'app (voir
/// `lib/app.dart`).
///
/// Règle d'or pour un débutant : si tu dois choisir une couleur, une taille
/// de texte ou un rayon de bordure dans un widget, demande-toi d'abord
/// "est-ce que ThemeData ou KoraColors le fournit déjà ?" avant d'écrire une
/// valeur en dur.
class AppTheme {
  const AppTheme._();

  static ThemeData get light => _buildTheme(
        brightness: Brightness.light,
        koraColors: KoraColors.light,
      );

  static ThemeData get dark => _buildTheme(
        brightness: Brightness.dark,
        koraColors: KoraColors.dark,
      );

  static ThemeData _buildTheme({
    required Brightness brightness,
    required KoraColors koraColors,
  }) {
    final isDark = brightness == Brightness.dark;

    // On part d'un ColorScheme de base généré par Flutter (light ou dark),
    // puis on écrase seulement les couleurs de la marque KORA avec
    // `copyWith`. C'est plus robuste que de construire un ColorScheme
    // "à la main" : Flutter se charge de remplir correctement tous les
    // champs qu'on ne précise pas.
    final baseScheme =
        isDark ? const ColorScheme.dark() : const ColorScheme.light();
    final colorScheme = baseScheme.copyWith(
      primary: AppColors.kora,
      onPrimary: AppColors.white,
      secondary: AppColors.gold,
      onSecondary: AppColors.kora,
      error: AppColors.danger,
      onError: AppColors.white,
      surface: koraColors.surface,
      onSurface: koraColors.textPrimary,
    );

    return ThemeData(
      brightness: brightness,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: koraColors.background,
      canvasColor: koraColors.background,
      splashFactory: InkRipple.splashFactory,

      // On enregistre KoraColors comme "extension" du thème : c'est ce
      // qui permet d'écrire `context.koraColors.surface` n'importe où.
      extensions: <ThemeExtension<dynamic>>[koraColors],

      appBarTheme: AppBarTheme(
        backgroundColor: Colors.transparent,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        foregroundColor: koraColors.textPrimary,
        // Valeurs alignées sur AppTextStyles.heading1 (voir ce fichier
        // pour le détail du calcul de letterSpacing depuis le CSS).
        titleTextStyle: TextStyle(
          color: koraColors.textPrimary,
          fontSize: 23,
          fontWeight: FontWeight.w800,
          letterSpacing: -0.575,
        ),
      ),

      cardTheme: CardThemeData(
        color: koraColors.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
          side: BorderSide(color: koraColors.line),
        ),
      ),

      dividerTheme: DividerThemeData(
        color: koraColors.line,
        thickness: 1,
        space: AppSpacing.lg,
      ),

      // Bouton principal (équivalent de `.primary` en CSS : fond gold,
      // texte kora, coins arrondis à 15, ombre douce).
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.gold,
          foregroundColor: AppColors.kora,
          minimumSize: const Size.fromHeight(50),
          padding: const EdgeInsets.symmetric(horizontal: 18),
          elevation: 0,
          textStyle: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w900,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
        ),
      ),

      // Bouton secondaire (équivalent `.ghost` : bordure fine, transparent).
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: koraColors.textPrimary,
          minimumSize: const Size.fromHeight(50),
          padding: const EdgeInsets.symmetric(horizontal: 18),
          side: BorderSide(color: koraColors.line),
          textStyle: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w900,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
        ),
      ),

      // Bouton texte (équivalent `.link-button` : pas de fond, couleur digital).
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.digital,
          textStyle: const TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        // CSS de référence (`.field-control`) : fond BLANC, pas gris — un
        // fond `mist` serait quasi identique au fond de page en mode
        // clair (`KoraColors.light.background` = `mist` aussi), rendant
        // le champ invisible faute de contraste. Voir aussi la bordure
        // ci-dessous, également absente auparavant pour la même raison.
        fillColor: isDark
            ? AppColors.white.withValues(alpha: 0.06)
            : AppColors.white,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: AppSpacing.md,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.input),
          borderSide: BorderSide(
            color: isDark ? koraColors.line : AppColors.inputBorderLight,
          ),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.input),
          borderSide: BorderSide(
            color: isDark ? koraColors.line : AppColors.inputBorderLight,
          ),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.input),
          borderSide: const BorderSide(color: AppColors.digital, width: 1.5),
        ),
        hintStyle: TextStyle(color: koraColors.textMuted),
      ),

      iconTheme: IconThemeData(color: koraColors.textPrimary),

      // Utilisé par [KoraBadge] (wrapper du `Badge` Material 3) — point de
      // notification doré, équivalent `.icon-btn .dot` /
      // `.notification-button::after` du prototype.
      badgeTheme: const BadgeThemeData(
        backgroundColor: AppColors.gold,
        textColor: AppColors.kora,
      ),

      // Utilisé par [KoraLoadingIndicator] (variante spinner, wrapper du
      // `CircularProgressIndicator` Material 3) — spinner doré partout
      // dans l'app, sans jamais passer `color:` en dur au widget.
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.gold,
      ),

      textTheme: _buildTextTheme(koraColors.textPrimary, koraColors.textMuted),
    );
  }

  static TextTheme _buildTextTheme(Color primary, Color muted) {
    return TextTheme(
      // Valeurs alignées sur AppTextStyles.heading1/heading2 (voir ce
      // fichier pour le détail du calcul de letterSpacing depuis le CSS).
      headlineSmall: TextStyle(
        fontSize: 23,
        fontWeight: FontWeight.w800,
        letterSpacing: -0.575,
        color: primary,
      ),
      titleLarge: TextStyle(
        fontSize: 17,
        fontWeight: FontWeight.w800,
        letterSpacing: -0.34,
        color: primary,
      ),
      titleMedium: TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w800,
        color: primary,
      ),
      bodyMedium: TextStyle(
        fontSize: 13,
        height: 1.45,
        color: primary,
      ),
      bodySmall: TextStyle(
        fontSize: 11,
        color: muted,
      ),
      labelSmall: TextStyle(
        fontSize: 10,
        fontWeight: FontWeight.w800,
        letterSpacing: 1.2,
        color: muted,
      ),
    );
  }
}
