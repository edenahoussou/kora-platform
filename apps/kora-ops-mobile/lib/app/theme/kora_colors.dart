import 'package:flutter/material.dart';
import 'app_colors.dart';

/// Couleurs **contextuelles** (clair/sombre), accessibles via
/// `context.koraColors` — contrairement à [AppColors] (couleurs de marque
/// fixes), celles-ci changent selon `Theme.of(context).brightness`.
/// Nomenclature alignée sur `kora-passager` (`KoraColors`,
/// `context.koraColors`) — voir document.md § Design System.
class KoraColors extends ThemeExtension<KoraColors> {
  const KoraColors({
    required this.surface,
    required this.background,
    required this.textPrimary,
    required this.textMuted,
    required this.line,
    required this.shadow,
  });

  final Color surface;
  final Color background;
  final Color textPrimary;
  final Color textMuted;
  final Color line;
  final Color shadow;

  static const KoraColors light = KoraColors(
    surface: AppColors.white,
    background: AppColors.mist,
    textPrimary: AppColors.kora,
    textMuted: Color(0x99001D4E),
    line: Color(0x1C001D4E),
    shadow: Color(0x24001D4E),
  );

  static const KoraColors dark = KoraColors(
    surface: Color(0xFF0B2A5C),
    background: AppColors.kora,
    textPrimary: Color(0xFFF4F6F8),
    textMuted: Color(0x99F4F6F8),
    line: Color(0x33FFFFFF),
    shadow: Color(0x40000000),
  );

  @override
  KoraColors copyWith({
    Color? surface,
    Color? background,
    Color? textPrimary,
    Color? textMuted,
    Color? line,
    Color? shadow,
  }) {
    return KoraColors(
      surface: surface ?? this.surface,
      background: background ?? this.background,
      textPrimary: textPrimary ?? this.textPrimary,
      textMuted: textMuted ?? this.textMuted,
      line: line ?? this.line,
      shadow: shadow ?? this.shadow,
    );
  }

  @override
  KoraColors lerp(ThemeExtension<KoraColors>? other, double t) {
    if (other is! KoraColors) return this;
    return KoraColors(
      surface: Color.lerp(surface, other.surface, t)!,
      background: Color.lerp(background, other.background, t)!,
      textPrimary: Color.lerp(textPrimary, other.textPrimary, t)!,
      textMuted: Color.lerp(textMuted, other.textMuted, t)!,
      line: Color.lerp(line, other.line, t)!,
      shadow: Color.lerp(shadow, other.shadow, t)!,
    );
  }
}

extension KoraColorsX on BuildContext {
  KoraColors get koraColors =>
      Theme.of(this).extension<KoraColors>() ?? KoraColors.light;
}
